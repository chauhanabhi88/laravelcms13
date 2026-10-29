<?php

declare(strict_types=1);

namespace Modules\Payment\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\ProvidesAppConfig;
use Modules\Payment\Console\ExpireUnpaidPaymentsCommand;
use Modules\Payment\Console\ReconcileSettlementsCommand;
use Modules\Payment\Contracts\CodRefundPayout;
use Modules\Payment\Contracts\CustomerOrderHistory;
use Modules\Payment\Contracts\PayableOrderResolver;
use Modules\Payment\Contracts\SettlementReport;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Drivers\RazorpayGateway;
use Modules\Payment\Events\RefundFailed;
use Modules\Payment\Events\RefundProcessed;
use Modules\Payment\Listeners\AlertFinanceOfFailedRefund;
use Modules\Payment\Listeners\SendRefundProcessedNotification;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentReconItem;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Models\WebhookEvent;
use Modules\Payment\Repositories\Cache\CachePaymentDecorator;
use Modules\Payment\Repositories\Cache\CachePaymentReconDecorator;
use Modules\Payment\Repositories\Cache\CachePaymentRefundDecorator;
use Modules\Payment\Repositories\Cache\CacheWebhookEventDecorator;
use Modules\Payment\Repositories\Eloquent\EloquentPaymentReconRepository;
use Modules\Payment\Repositories\Eloquent\EloquentPaymentRefundRepository;
use Modules\Payment\Repositories\Eloquent\EloquentPaymentRepository;
use Modules\Payment\Repositories\Eloquent\EloquentWebhookEventRepository;
use Modules\Payment\Repositories\PaymentReconRepository;
use Modules\Payment\Repositories\PaymentRefundRepository;
use Modules\Payment\Repositories\PaymentRepository;
use Modules\Payment\Repositories\WebhookEventRepository;
use Modules\Payment\Support\ManualCodRefundPayout;
use Modules\Payment\Support\NullPayableOrderResolver;
use Modules\Payment\Support\PaymentAppConfig;
use Modules\Payment\Support\PaymentOrderHistory;

class PaymentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Event::listen(RefundProcessed::class, SendRefundProcessedNotification::class);
        Event::listen(RefundFailed::class, AlertFinanceOfFailedRefund::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireUnpaidPaymentsCommand::class, ReconcileSettlementsCommand::class]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        $this->app->tag([PaymentAppConfig::class], ProvidesAppConfig::TAG);
        $this->app->bind(RazorpayGateway::class, fn (): RazorpayGateway => new RazorpayGateway((array) config('payment.razorpay')));
        $this->app->bind(SettlementReport::class, RazorpayGateway::class);
        // One instance per app so simulate() and fetch() share state within a request / test.
        $this->app->singleton(FakeGateway::class);

        // Defaults until later modules bind their own: Order (M12) resolves orders and may
        // rebind the order history; Finance (M17) pays COD refunds out.
        $this->app->bindIf(PayableOrderResolver::class, NullPayableOrderResolver::class);
        $this->app->bindIf(CustomerOrderHistory::class, PaymentOrderHistory::class);
        $this->app->bindIf(CodRefundPayout::class, ManualCodRefundPayout::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/payment.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'payment');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/payment'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'payment');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/payment');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'payment');
    }

    /**
     * Admin grid repositories, wrapped in the (pass-through) cache decorators when the
     * module's "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $this->app->bind(PaymentRepository::class, function () {
            $repository = new EloquentPaymentRepository(new Payment);

            return getModule('payment', 'cache') ? new CachePaymentDecorator($repository) : $repository;
        });

        $this->app->bind(PaymentRefundRepository::class, function () {
            $repository = new EloquentPaymentRefundRepository(new PaymentRefund);

            return getModule('payment', 'cache') ? new CachePaymentRefundDecorator($repository) : $repository;
        });

        $this->app->bind(WebhookEventRepository::class, function () {
            $repository = new EloquentWebhookEventRepository(new WebhookEvent);

            return getModule('payment', 'cache') ? new CacheWebhookEventDecorator($repository) : $repository;
        });

        $this->app->bind(PaymentReconRepository::class, function () {
            $repository = new EloquentPaymentReconRepository(new PaymentReconItem);

            return getModule('payment', 'cache') ? new CachePaymentReconDecorator($repository) : $repository;
        });
    }
}
