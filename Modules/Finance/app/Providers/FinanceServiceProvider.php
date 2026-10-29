<?php

declare(strict_types=1);

namespace Modules\Finance\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Finance\Console\BackfillLedgerCommand;
use Modules\Finance\Console\BuildPayoutBatchCommand;
use Modules\Finance\Console\GenerateFeeInvoicesCommand;
use Modules\Finance\Console\ImportCodRemittancesCommand;
use Modules\Finance\Console\ReleaseEligibleEntriesCommand;
use Modules\Finance\Drivers\FakePayoutGateway;
use Modules\Finance\Listeners\CreditResellerMargin;
use Modules\Finance\Listeners\GenerateTaxInvoiceOnReadyToShip;
use Modules\Finance\Listeners\HandleRazorpayXPayoutWebhook;
use Modules\Finance\Listeners\IssueCreditNoteOnReturn;
use Modules\Finance\Listeners\PostClaimCredit;
use Modules\Finance\Listeners\PostLogisticsCharges;
use Modules\Finance\Listeners\PostPenalty;
use Modules\Finance\Listeners\PostReturnReversal;
use Modules\Finance\Listeners\PostSaleOnDelivered;
use Modules\Finance\Listeners\SyncPayoutHolds;
use Modules\Finance\Models\CodRemittance;
use Modules\Finance\Models\CommissionRule;
use Modules\Finance\Models\FeeRule;
use Modules\Finance\Models\PayoutBatch;
use Modules\Finance\Models\PayoutHold;
use Modules\Finance\Models\ResellerWithdrawal;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Models\SellerPayout;
use Modules\Finance\Models\TaxDocument;
use Modules\Finance\Repositories\Cache\CacheFinanceGridDecorator;
use Modules\Finance\Repositories\Eloquent\EloquentFinanceGridRepository;
use Modules\Finance\Repositories\FinanceGridRepository;
use Modules\Finance\Services\FeeCalculatorImpl;
use Modules\Finance\Services\LedgerPosterImpl;
use Modules\Finance\Support\FinanceDashboardComposer;
use Modules\Finance\Support\GatewayCodRefundPayout;
use Modules\Logistics\Events\ShipmentRtoDelivered;
use Modules\Order\Contracts\LedgerPoster;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Events\SellerPenaltyIncurred;
use Modules\Payment\Contracts\CodRefundPayout;
use Modules\Product\Contracts\FeeCalculator;
use Modules\Returns\Events\ReturnClaimDecided;
use Modules\Returns\Events\ReturnRefunded;
use Modules\Seller\Events\RazorpayXWebhookReceived;
use Modules\Seller\Events\SellerApproved;
use Modules\Seller\Events\SellerBankAccountChanged;
use Modules\Seller\Events\SellerReactivated;
use Modules\Seller\Events\SellerSuspended;
use Modules\Seller\Policies\SellerOwnedPolicy;

class FinanceServiceProvider extends ServiceProvider
{
    /**
     * Admin grids served by the shared finance grid repository: config cache key => model.
     *
     * @var array<string, class-string>
     */
    public const GRIDS = [
        'commission_rule' => CommissionRule::class,
        'fee_rule' => FeeRule::class,
        'ledger' => SellerLedgerEntry::class,
        'payout_batch' => PayoutBatch::class,
        'payout_hold' => PayoutHold::class,
        'tax_document' => TaxDocument::class,
        'cod_remittance' => CodRemittance::class,
        'reseller_withdrawal' => ResellerWithdrawal::class,
    ];

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Supplier panel tenancy: another seller's payout, document or entry answers 404.
        Gate::policy(SellerPayout::class, SellerOwnedPolicy::class);
        Gate::policy(TaxDocument::class, SellerOwnedPolicy::class);
        Gate::policy(SellerLedgerEntry::class, SellerOwnedPolicy::class);

        $this->registerListeners();
        $this->registerSellerPanel();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReleaseEligibleEntriesCommand::class,
                BuildPayoutBatchCommand::class,
                GenerateFeeInvoicesCommand::class,
                ImportCodRemittancesCommand::class,
                BackfillLedgerCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // The M04/M12/M11 stubs (bound with bindIf) give way to the finance engine.
        $this->app->bind(FeeCalculator::class, FeeCalculatorImpl::class);
        $this->app->bind(LedgerPoster::class, LedgerPosterImpl::class);
        $this->app->bind(CodRefundPayout::class, GatewayCodRefundPayout::class);

        // One fake rail per process, so tests can inspect what was sent.
        $this->app->singleton(FakePayoutGateway::class);

        $this->app->bind(FinanceGridRepository::class, function ($app, array $parameters) {
            $key = (string) ($parameters['grid'] ?? 'ledger');
            $model = self::GRIDS[$key] ?? SellerLedgerEntry::class;
            $repository = new EloquentFinanceGridRepository(new $model, $key);

            return getModule('finance', 'cache') ? new CacheFinanceGridDecorator($repository) : $repository;
        });
    }

    protected function registerListeners(): void
    {
        Event::listen(SellerOrderStatusChanged::class, PostSaleOnDelivered::class);
        Event::listen(SellerOrderStatusChanged::class, CreditResellerMargin::class);
        Event::listen(SellerOrderStatusChanged::class, GenerateTaxInvoiceOnReadyToShip::class);
        Event::listen(ShipmentRtoDelivered::class, PostLogisticsCharges::class);
        Event::listen(ReturnRefunded::class, PostReturnReversal::class);
        Event::listen(ReturnRefunded::class, IssueCreditNoteOnReturn::class);
        Event::listen(SellerPenaltyIncurred::class, PostPenalty::class);
        Event::listen(ReturnClaimDecided::class, PostClaimCredit::class);
        Event::listen(RazorpayXWebhookReceived::class, HandleRazorpayXPayoutWebhook::class);

        foreach ([SellerBankAccountChanged::class, SellerSuspended::class, SellerReactivated::class, SellerApproved::class] as $event) {
            Event::listen($event, SyncPayoutHolds::class);
        }
    }

    /**
     * Supplier panel: a "Payments" sidebar section (finance staff and owners), the next payout
     * on the dashboard card.
     */
    protected function registerSellerPanel(): void
    {
        $menu = (array) config('theme.seller_menu', []);
        $menu['payments'] = [
            'label' => 'finance::seller.menu.section',
            'items' => [
                ['route' => 'seller.payments.overview', 'label' => 'finance::seller.menu.overview', 'icon' => 'wallet', 'active' => ['seller.payments.overview', 'seller.payments.order_settlement']],
                ['route' => 'seller.payments.statement', 'label' => 'finance::seller.menu.statement', 'icon' => 'document', 'active' => 'seller.payments.statement*'],
                ['route' => 'seller.payments.payouts', 'label' => 'finance::seller.menu.payouts', 'icon' => 'banknote', 'active' => 'seller.payments.payouts*'],
                ['route' => 'seller.payments.invoices', 'label' => 'finance::seller.menu.invoices', 'icon' => 'download', 'active' => 'seller.payments.invoices*'],
            ],
        ];

        config(['theme.seller_menu' => $menu]);

        View::composer('seller::seller.dashboard.index', FinanceDashboardComposer::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/finance.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'finance');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/finance'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'finance');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/finance');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'finance');
    }
}
