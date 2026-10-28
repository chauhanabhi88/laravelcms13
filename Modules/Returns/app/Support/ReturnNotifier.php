<?php

declare(strict_types=1);

namespace Modules\Returns\Support;

use Modules\Customer\Models\Customer;
use Modules\Notification\Services\NotificationHub;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Models\ReturnRequest;
use Modules\Seller\Models\Seller;
use Throwable;

/**
 * Return notifications from the M09 catalogue. A notification failure never undoes the
 * return action, so every send is reported and swallowed.
 */
class ReturnNotifier
{
    public function __construct(private readonly NotificationHub $hub) {}

    public function requested(ReturnRequest $return): void
    {
        $this->toCustomer('return.requested', $return, [
            'order_no' => $this->orderNo($return),
            'type' => trans('returns::returns.types.'.$return->type),
        ]);
    }

    /**
     * Held for a CX agent (not approved automatically).
     */
    public function held(ReturnRequest $return): void
    {
        $this->toAdmins('admin.return.review', [
            'return_no' => $return->return_no,
            'order_no' => $this->orderNo($return),
            'reason' => ReturnOptions::reason($return->reason_code),
            'url' => route('admin.return_request.show', updateUrlParams([$return->id])),
        ]);
    }

    public function approved(ReturnRequest $return): void
    {
        $date = $return->pickup_scheduled_at ?? now()->addDay();

        $this->toCustomer('return.approved', $return, [
            'order_no' => $this->orderNo($return),
            'pickup_date' => $date->format('D, j M'),
        ]);
    }

    public function rejected(ReturnRequest $return, string $reason): void
    {
        $this->toCustomer('return.rejected', $return, ['reason' => $reason]);
    }

    public function pickedUp(ReturnRequest $return): void
    {
        $this->toCustomer('return.picked_up', $return, []);

        try {
            $seller = Seller::query()->find($return->seller_id);

            if ($seller !== null) {
                $this->hub->eventForSeller('seller.return.received', $seller, [
                    'return_no' => $return->return_no,
                    'order_no' => $this->orderNo($return),
                    'url' => frontRoute('seller.return.show', ['returnNo' => $return->return_no]),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function claimOpened(ReturnClaim $claim): void
    {
        $claim->loadMissing(['returnRequest', 'sellerOrder', 'seller']);

        $this->toAdmins('admin.return.claim', [
            'order_no' => (string) ($claim->returnRequest?->return_no ?? $claim->sellerOrder?->sub_order_no),
            'store' => (string) $claim->seller?->displayName(),
            'reason' => trans('returns::return_claim.reasons.'.$claim->reason),
            'url' => route('admin.return_claim.show', updateUrlParams([$claim->id])),
        ]);
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function toCustomer(string $key, ReturnRequest $return, array $data): void
    {
        try {
            $customer = Customer::query()->find($return->customer_id);

            if ($customer === null) {
                return;
            }

            $this->hub->event($key, $customer, $data + [
                'return_no' => $return->return_no,
                'url' => frontRoute('customer.returns.show', ['returnNo' => $return->return_no]),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function toAdmins(string $key, array $data): void
    {
        try {
            $this->hub->eventForAdmins($key, $data);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function orderNo(ReturnRequest $return): string
    {
        $return->loadMissing('sellerOrder');

        return (string) $return->sellerOrder?->sub_order_no;
    }
}
