<?php

declare(strict_types=1);

namespace Modules\Payment\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Payment\Contracts\CodRiskCheck;
use Modules\Payment\Contracts\CustomerOrderHistory;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\DTO\CodEligibility;
use Modules\Payment\Models\Payment;
use Modules\Payment\Support\PaymentSettings;

/**
 * Cash on Delivery eligibility (FR-PAY-004, plan M11 §5.3). Every rule must pass, checked in
 * this order so the buyer sees the most actionable reason:
 * COD enabled → pincode allows COD → order value within min/max → every item allows COD →
 * customer not COD-blocked → daily COD limit → tagged CodRiskCheck rules (M12).
 *
 * otpRequired: the order total reaches the OTP threshold, or the customer has no delivered
 * order yet (when that setting is on).
 */
class CodEligibilityService
{
    public const RISK_CHECK_TAG = 'payment.cod_risk_checks';

    public function __construct(
        private readonly Container $container,
        private readonly CustomerOrderHistory $history,
    ) {}

    /**
     * @param  int|null  $salesOrderId  an existing order whose own payments do not count towards the daily limit
     */
    public function check(Money $orderTotal, Customer $customer, ?string $pincode, bool $itemsAllowCod, ?int $salesOrderId = null): CodEligibility
    {
        if (! PaymentSettings::codEnabled()) {
            return $this->block(CodEligibility::REASON_DISABLED);
        }

        if (! $this->pincodeAllowsCod($pincode)) {
            return $this->block(CodEligibility::REASON_PINCODE, ['pincode' => (string) $pincode]);
        }

        if ($orderTotal->compare(PaymentSettings::codMaxValue()) > 0) {
            return $this->block(CodEligibility::REASON_ABOVE_MAX, ['amount' => PaymentSettings::codMaxValue()->format()]);
        }

        if ($orderTotal->compare(PaymentSettings::codMinValue()) < 0) {
            return $this->block(CodEligibility::REASON_BELOW_MIN, ['amount' => PaymentSettings::codMinValue()->format()]);
        }

        if (! $itemsAllowCod) {
            return $this->block(CodEligibility::REASON_ITEMS);
        }

        if ($customer->isCodBlocked()) {
            return $this->block(CodEligibility::REASON_CUSTOMER_BLOCKED);
        }

        $dailyLimit = PaymentSettings::codDailyLimit();

        if ($dailyLimit > 0 && $this->codOrdersToday($customer, $salesOrderId) >= $dailyLimit) {
            return $this->block(CodEligibility::REASON_DAILY_LIMIT, ['count' => $dailyLimit]);
        }

        foreach ($this->container->tagged(self::RISK_CHECK_TAG) as $rule) {
            /** @var CodRiskCheck $rule */
            $reason = $rule->blockReason($customer, $orderTotal, $pincode);

            if ($reason !== null) {
                return CodEligibility::block(CodEligibility::REASON_RISK, $reason);
            }
        }

        return CodEligibility::allow($this->otpRequired($orderTotal, $customer));
    }

    /**
     * Eligibility of an existing order (the pay page's "Pay with COD instead").
     */
    public function checkOrder(PayableOrder $order): CodEligibility
    {
        return $this->check(
            $order->payableAmount(Payment::METHOD_COD),
            $order->payableCustomer(),
            $order->deliveryPincode(),
            $order->allItemsAllowCod(),
            $order->payableOrderId(),
        );
    }

    public function otpRequired(Money $orderTotal, Customer $customer): bool
    {
        if ($orderTotal->compare(PaymentSettings::codOtpThreshold()) >= 0) {
            return true;
        }

        return PaymentSettings::codOtpForNewCustomers() && ! $this->history->hasDeliveredOrders($customer);
    }

    protected function pincodeAllowsCod(?string $pincode): bool
    {
        if ($pincode === null || ! preg_match('/^\d{6}$/', $pincode)) {
            return false;
        }

        // Pincode-level flags are kept identical on every post office of the pincode.
        $office = DirectoryPincode::query()->where('pincode', $pincode)->first(['id', 'is_serviceable', 'cod_allowed']);

        return $office !== null && $office->isServiceable() && $office->allowsCod();
    }

    /**
     * COD payments the customer started today that are still alive.
     */
    protected function codOrdersToday(Customer $customer, ?int $salesOrderId): int
    {
        return Payment::query()
            ->where('customer_id', $customer->id)
            ->where('method', Payment::METHOD_COD)
            ->where('created_at', '>=', Carbon::today())
            ->whereNotIn('status', [Payment::STATUS_CANCELLED, Payment::STATUS_FAILED])
            ->when($salesOrderId !== null, fn ($query) => $query->where('sales_order_id', '!=', $salesOrderId))
            ->distinct()
            ->count('sales_order_id');
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    private function block(string $code, array $replace = []): CodEligibility
    {
        return CodEligibility::block($code, trans('payment::front.cod.reasons.'.$code, $replace));
    }
}
