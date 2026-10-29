<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Finance\Exceptions\FinanceException;
use Modules\Finance\Models\ResellerWalletEntry;
use Modules\Finance\Models\ResellerWithdrawal;
use Modules\Finance\Services\ResellerWalletService;
use Modules\Finance\Support\FinanceSettings;
use Modules\Returns\Support\Validation\RefundDetailsRules;

/**
 * Reseller earnings for the mobile apps (FR-FIN-007, plan M20 §4): the wallet balances and
 * history, and a withdrawal to a UPI id or bank account. Amounts are two-place decimal strings.
 */
class EarningsController extends ApiController
{
    public function __construct(private readonly ResellerWalletService $wallet) {}

    public function show(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $balances = $this->wallet->balances($customer->id);
        $entries = ResellerWalletEntry::query()->forCustomer($customer->id)->latest('id')->paginate($this->perPage((int) config('finance.customer_per_page', 15)));
        $withdrawals = ResellerWithdrawal::query()->forCustomer($customer->id)->latest('id')->limit(10)->get();

        return $this->ok(
            [
                'is_reseller' => $customer->isReseller(),
                'balances' => [
                    'available' => $balances['available']->toDecimal(),
                    'pending' => $balances['pending']->toDecimal(),
                    'paid' => $balances['paid']->toDecimal(),
                ],
                'minimum_withdrawal' => FinanceSettings::resellerMinWithdrawal()->toDecimal(),
                'entries' => $entries->getCollection()->map(fn (ResellerWalletEntry $entry): array => [
                    'id' => $entry->id,
                    'type' => $entry->type,
                    'direction' => $entry->direction,
                    'amount' => $entry->amount->toDecimal(),
                    'status' => $entry->status,
                    'description' => $entry->description,
                    'eligible_on' => $entry->eligible_on?->toDateString(),
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])->values()->all(),
                'withdrawals' => $withdrawals->map(fn (ResellerWithdrawal $withdrawal): array => $this->withdrawal($withdrawal))->values()->all(),
            ],
            [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        );
    }

    public function withdraw(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:9999999']] + RefundDetailsRules::rules(true));

        try {
            $withdrawal = $this->wallet->withdraw(
                $customer,
                Money::of((string) $validated['amount']),
                (string) $validated['refund_mode'],
                (array) RefundDetailsRules::beneficiary($validated),
            );
        } catch (FinanceException $e) {
            return $this->failReason($e->reasonCode, $e->getMessage(), ['amount' => [$e->getMessage()]]);
        }

        return $this->created(
            $this->withdrawal($withdrawal),
            message: trans('finance::wallet.messages.requested', ['no' => $withdrawal->withdrawal_no, 'amount' => $withdrawal->amount->format()]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function withdrawal(ResellerWithdrawal $withdrawal): array
    {
        return [
            'withdrawal_no' => $withdrawal->withdrawal_no,
            'amount' => $withdrawal->amount->toDecimal(),
            'mode' => $withdrawal->mode,
            'to' => $withdrawal->beneficiary_label,
            'status' => $withdrawal->status,
            'utr' => $withdrawal->utr,
            'created_at' => $withdrawal->created_at?->toIso8601String(),
            'paid_at' => $withdrawal->paid_at?->toIso8601String(),
        ];
    }

    protected function customer(Request $request): Customer
    {
        /** @var Customer */
        return $request->user('api');
    }
}
