<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Seller\Http\Requests\Seller\BankAccountRequest;
use Modules\Seller\Models\SellerBankAccount;
use Modules\Seller\Repositories\SellerBankAccountRepository;
use Modules\Seller\Services\BankAccountService;
use Modules\Seller\Support\SellerSettings;

/**
 * Settings › Bank. A new account number re-verifies and puts payouts on hold until it passes.
 */
class BankAccountController extends SellerPanelController
{
    public function __construct(
        private readonly SellerBankAccountRepository $accounts,
        private readonly BankAccountService $bankAccounts,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $seller = $this->seller();
        $accounts = $this->accounts->forSeller($seller->id);
        $primary = $accounts->first(fn (SellerBankAccount $account): bool => $account->is_primary === (int) config('core.yes'));

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $primary?->verification_status,
                'label' => $primary?->statusLabel(),
                'done' => $primary !== null && ! $primary->isPending(),
            ]);
        }

        return view('seller::seller.settings.bank', [
            'seller' => $seller,
            'sellerStoreName' => $seller->displayName(),
            'primary' => $primary,
            'history' => $accounts->reject(fn (SellerBankAccount $account): bool => $account->is($primary))->values(),
            'accountTypes' => collect((array) config('seller.bank_account_types'))
                ->mapWithKeys(fn (string $type): array => [$type => trans('seller::seller.account_types.'.$type)])
                ->all(),
            'usesPennyDrop' => in_array(SellerSettings::bankVerifier(), ['razorpayx', 'fake'], true),
        ]);
    }

    public function store(BankAccountRequest $request): RedirectResponse
    {
        $account = $this->bankAccounts->save($this->seller(), $request->bankData(), $request->file('cancelled_cheque'));

        return redirect()->to(frontRoute('seller.bank.index'))->with(
            $account->verification_status === SellerBankAccount::STATUS_FAILED ? 'error' : 'success',
            trans('seller::panel.bank.messages.'.$account->verification_status),
        );
    }

    /**
     * Retry verification of the primary account (after a provider outage or a failed check).
     */
    public function verify(Request $request): RedirectResponse
    {
        $account = SellerBankAccount::query()->findOrFail((int) $request->route('account'));
        $this->authorizeOwned($account);

        if ($account->isVerified() || $account->is_primary !== (int) config('core.yes')) {
            return redirect()->to(frontRoute('seller.bank.index'));
        }

        $account = $this->bankAccounts->verify($account);

        return redirect()->to(frontRoute('seller.bank.index'))->with(
            $account->verification_status === SellerBankAccount::STATUS_FAILED ? 'error' : 'success',
            trans('seller::panel.bank.messages.'.$account->verification_status),
        );
    }
}
