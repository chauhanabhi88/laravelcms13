<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\ResellerWalletEntry;
use Modules\Finance\Models\ResellerWithdrawal;
use Modules\Finance\Services\ResellerWalletService;
use Modules\Finance\Support\FinanceSettings;
use Modules\Returns\Support\Validation\RefundDetailsRules;

/**
 * Reseller earnings (FR-FIN-007, plan M17 §5.8): available, pending (return window open) and
 * paid margin, the wallet history, and a withdrawal to a UPI id or bank account.
 */
class ResellerEarningsController extends Controller
{
    public function __construct(private readonly ResellerWalletService $wallet) {}

    public function index(Request $request): View
    {
        $customer = $this->customer($request);

        return view('finance::account.earnings', [
            'customer' => $customer,
            'isReseller' => $customer->isReseller(),
            'balances' => $this->wallet->balances($customer->id),
            'minimum' => FinanceSettings::resellerMinWithdrawal(),
            'entries' => ResellerWalletEntry::query()->forCustomer($customer->id)->latest('id')->paginate((int) config('finance.customer_per_page', 15)),
            'withdrawals' => ResellerWithdrawal::query()->forCustomer($customer->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $customer = $this->customer($request);
        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:9999999']] + RefundDetailsRules::rules(true));
        $back = redirect(frontRoute('customer.earnings.index'));

        try {
            $withdrawal = $this->wallet->withdraw(
                $customer,
                Money::of((string) $validated['amount']),
                (string) $validated['refund_mode'],
                (array) RefundDetailsRules::beneficiary($validated),
            );
        } catch (FinanceException $e) {
            return $back->withInput($request->except(['account_number', 'account_number_confirmation']))->withErrors(['amount' => $e->getMessage()]);
        }

        return $back->with('success', trans('finance::wallet.messages.requested', ['no' => $withdrawal->withdrawal_no, 'amount' => $withdrawal->amount->format()]));
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user('customer');
        abort_unless($customer instanceof Customer, 401);

        return $customer;
    }
}
