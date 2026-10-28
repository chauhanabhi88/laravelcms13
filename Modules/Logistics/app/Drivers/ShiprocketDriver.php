<?php

declare(strict_types=1);

namespace Modules\Logistics\Drivers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Support\Money;
use Modules\Logistics\Contracts\CourierDriver;
use Modules\Logistics\DTO\AwbResult;
use Modules\Logistics\DTO\CarrierQuote;
use Modules\Logistics\DTO\PickupResult;
use Modules\Logistics\DTO\ProviderShipment;
use Modules\Logistics\DTO\ServiceabilityRequest;
use Modules\Logistics\DTO\ServiceabilityResult;
use Modules\Logistics\DTO\TrackingEvent;
use Modules\Logistics\Exceptions\CourierException;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Support\Shiprocket\ShiprocketClient;
use Modules\Logistics\Support\Shiprocket\ShiprocketStatusMap;
use Modules\Order\Models\SalesOrderAddress;
use Modules\Order\Models\SalesOrderItem;
use Modules\Seller\Models\SellerWarehouse;

/**
 * Shiprocket aggregator (plan M13 §5.3): 25+ carriers behind one API. Endpoints:
 * serviceability, orders/create/adhoc, courier/assign/awb, courier/generate/pickup|label,
 * manifests/generate + print, courier/track/awb, orders/cancel, orders/create/return,
 * settings/company/addpickup and the NDR action API.
 */
class ShiprocketDriver implements CourierDriver
{
    public function __construct(private readonly ShiprocketClient $client) {}

    public function client(): ShiprocketClient
    {
        return $this->client;
    }

    public function serviceability(ServiceabilityRequest $request): ServiceabilityResult
    {
        $query = [
            'pickup_postcode' => $request->originPincode,
            'delivery_postcode' => $request->destinationPincode,
            'weight' => $this->kilograms($request->bucketedWeightGrams()),
            'cod' => $request->cod ? 1 : 0,
        ];

        if ($request->declaredValue !== null) {
            $query['declared_value'] = $request->declaredValue;
        }

        $body = $this->client->get('/v1/external/courier/serviceability/', $query, $request->timeoutSeconds);
        $data = (array) ($body['data'] ?? []);

        $carriers = [];
        foreach ((array) ($data['available_courier_companies'] ?? []) as $company) {
            if (! is_array($company) || ! isset($company['courier_company_id'])) {
                continue;
            }

            $carriers[] = new CarrierQuote(
                (string) $company['courier_company_id'],
                (string) ($company['courier_name'] ?? 'Courier '.$company['courier_company_id']),
                Money::of((string) ($company['rate'] ?? $company['freight_charge'] ?? '0')),
                Money::of((string) ($company['cod_charges'] ?? '0')),
                is_numeric($company['estimated_delivery_days'] ?? null) ? (int) $company['estimated_delivery_days'] : null,
                (int) ($company['cod'] ?? 0) === 1,
            );
        }

        $recommended = $data['recommended_courier_company_id'] ?? null;

        return new ServiceabilityResult($carriers, $recommended !== null && $recommended !== '' ? (string) $recommended : null);
    }

    public function createShipment(Shipment $shipment): ProviderShipment
    {
        $shipment->loadMissing(['sellerOrder.salesOrder.customer', 'sellerOrder.salesOrder.addresses', 'sellerOrder.items', 'sellerOrder.warehouse']);
        $sellerOrder = $shipment->sellerOrder;
        $order = $sellerOrder->salesOrder;
        $shipping = $order->addresses->firstWhere('type', SalesOrderAddress::TYPE_SHIPPING);
        $billing = $order->addresses->firstWhere('type', SalesOrderAddress::TYPE_BILLING) ?? $shipping;

        if ($shipping === null) {
            throw new CourierException(trans('logistics::logistics.errors.no_shipping_address'), 'invalid_order');
        }

        $pickupLocation = $shipment->pickup_location_code ?? $sellerOrder->warehouse?->courier_pickup_ref;

        if ($pickupLocation === null || $pickupLocation === '') {
            throw new CourierException(trans('logistics::logistics.errors.no_pickup_location'), 'no_pickup_location');
        }

        $items = $sellerOrder->items->reject(fn (SalesOrderItem $item): bool => $item->isCancelled());
        $email = $order->customer?->email ?: config('mail.from.address');

        $payload = [
            'order_id' => $sellerOrder->sub_order_no,
            'order_date' => ($order->placed_at ?? $order->created_at ?? Carbon::now())->format('Y-m-d H:i'),
            'pickup_location' => $pickupLocation,
            'billing_customer_name' => $this->firstName($billing->name),
            'billing_last_name' => $this->lastName($billing->name),
            'billing_address' => $billing->address_line1,
            'billing_address_2' => trim(($billing->address_line2 ?? '').' '.($billing->landmark ?? '')),
            'billing_city' => (string) $billing->city_name,
            'billing_pincode' => $billing->pincode,
            'billing_state' => (string) $billing->state_name,
            'billing_country' => config('logistics.shiprocket.country', 'India'),
            'billing_email' => $email,
            'billing_phone' => $this->phone($billing->phone),
            'shipping_is_billing' => $billing->id === $shipping->id,
            'shipping_customer_name' => $this->firstName($shipping->name),
            'shipping_last_name' => $this->lastName($shipping->name),
            'shipping_address' => $shipping->address_line1,
            'shipping_address_2' => trim(($shipping->address_line2 ?? '').' '.($shipping->landmark ?? '')),
            'shipping_city' => (string) $shipping->city_name,
            'shipping_pincode' => $shipping->pincode,
            'shipping_state' => (string) $shipping->state_name,
            'shipping_country' => config('logistics.shiprocket.country', 'India'),
            'shipping_email' => $email,
            'shipping_phone' => $this->phone($shipping->phone),
            'order_items' => $items->map(fn (SalesOrderItem $item): array => [
                'name' => Str::limit(trim($item->name.' '.($item->variant_label ?? '')), 190, ''),
                'sku' => $item->sku,
                'units' => max(1, $item->quantity - $item->cancelled_qty),
                'selling_price' => ($item->customer_price !== null && $shipment->isCod() ? $item->customer_price : $item->price)->toDecimal(),
                'discount' => '0',
                'tax' => (string) $item->gst_percent,
                'hsn' => (string) $item->hsn_code,
            ])->values()->all(),
            'payment_method' => $shipment->isCod() ? 'COD' : 'Prepaid',
            'sub_total' => $shipment->isCod() ? $shipment->cod_amount->toDecimal() : $sellerOrder->items_subtotal->toDecimal(),
            'length' => (float) ($shipment->length_cm ?? config('logistics.package.default_length_cm')),
            'breadth' => (float) ($shipment->breadth_cm ?? config('logistics.package.default_breadth_cm')),
            'height' => (float) ($shipment->height_cm ?? config('logistics.package.default_height_cm')),
            'weight' => $this->kilograms($shipment->weight_grams),
        ];

        $body = $this->client->post('/v1/external/orders/create/adhoc', $payload);

        if (! isset($body['order_id'])) {
            throw new CourierException(trans('logistics::logistics.errors.provider', ['message' => (string) ($body['message'] ?? 'order not created')]));
        }

        return new ProviderShipment((string) $body['order_id'], isset($body['shipment_id']) ? (string) $body['shipment_id'] : null);
    }

    public function assignAwb(Shipment $shipment, ?string $carrierId): AwbResult
    {
        $payload = ['shipment_id' => $this->providerShipmentId($shipment)];

        if ($carrierId !== null && $carrierId !== '') {
            $payload['courier_id'] = $carrierId;
        }

        $body = $this->client->post('/v1/external/courier/assign/awb', $payload);
        $data = (array) data_get($body, 'response.data', []);
        $awb = $data['awb_code'] ?? null;

        if ((int) ($body['awb_assign_status'] ?? 0) !== 1 || ! is_scalar($awb) || (string) $awb === '') {
            $message = data_get($body, 'response.data.awb_assign_error') ?? $body['message'] ?? 'AWB not assigned';

            throw new CourierException(trans('logistics::logistics.errors.provider', ['message' => (string) $message]), 'awb_failed');
        }

        $weight = $data['applied_weight'] ?? null;

        return new AwbResult(
            (string) $awb,
            isset($data['courier_company_id']) ? (string) $data['courier_company_id'] : $carrierId,
            isset($data['courier_name']) ? (string) $data['courier_name'] : null,
            is_numeric($weight) ? (int) round((float) $weight * 1000) : null,
            null,
        );
    }

    public function generateLabel(array $shipments): ?string
    {
        $body = $this->client->post('/v1/external/courier/generate/label', ['shipment_id' => $this->providerShipmentIds($shipments)]);
        $url = $body['label_url'] ?? null;

        if ((int) ($body['label_created'] ?? 0) !== 1 || ! is_string($url) || $url === '') {
            throw new CourierException(trans('logistics::logistics.errors.provider', ['message' => (string) ($body['response'] ?? $body['message'] ?? 'label not created')]));
        }

        return $url;
    }

    public function generateManifest(array $shipments): ?string
    {
        $ids = $this->providerShipmentIds($shipments);
        $body = $this->client->post('/v1/external/manifests/generate', ['shipment_id' => $ids]);
        $url = $body['manifest_url'] ?? null;

        if (! is_string($url) || $url === '') {
            $orderIds = array_values(array_filter(array_map(fn (Shipment $shipment): ?string => $shipment->provider_order_id, $shipments)));
            $body = $this->client->post('/v1/external/manifests/print', ['order_ids' => $orderIds]);
            $url = $body['manifest_url'] ?? null;
        }

        if (! is_string($url) || $url === '') {
            throw new CourierException(trans('logistics::logistics.errors.provider', ['message' => (string) ($body['message'] ?? 'manifest not created')]));
        }

        return $url;
    }

    public function schedulePickup(array $shipments, Carbon $date): PickupResult
    {
        $body = $this->client->post('/v1/external/courier/generate/pickup', [
            'shipment_id' => $this->providerShipmentIds($shipments),
            'pickup_date' => [$date->toDateString()],
        ]);

        if ((int) ($body['pickup_status'] ?? 0) !== 1) {
            throw new CourierException(trans('logistics::logistics.errors.provider', ['message' => (string) (data_get($body, 'response.data') ?? $body['message'] ?? 'pickup not scheduled')]));
        }

        $scheduled = data_get($body, 'response.pickup_scheduled_date');

        return new PickupResult(
            true,
            is_string($scheduled) && $scheduled !== '' ? Carbon::parse($scheduled) : $date->copy(),
            is_scalar($token = data_get($body, 'response.pickup_token_number')) ? (string) $token : null,
        );
    }

    public function track(Shipment $shipment): array
    {
        if (! $shipment->isBooked()) {
            return [];
        }

        $body = $this->client->get('/v1/external/courier/track/awb/'.rawurlencode((string) $shipment->awb));
        $tracking = (array) ($body['tracking_data'] ?? []);

        $events = [];
        foreach ((array) ($tracking['shipment_track_activities'] ?? []) as $activity) {
            if (is_array($activity) && ($event = self::eventFromScan($activity)) !== null) {
                $events[] = $event;
            }
        }

        if ($events === [] && is_array($current = data_get($tracking, 'shipment_track.0'))) {
            $label = $current['current_status'] ?? null;
            $at = $current['updated_time_stamp'] ?? $current['delivered_date'] ?? null;

            if (is_string($label) && $label !== '' && is_string($at) && $at !== '') {
                $events[] = new TrackingEvent(ShiprocketStatusMap::map($label), mb_substr($label, 0, 60), Carbon::parse($at), '', null, null, $current);
            }
        }

        usort($events, fn (TrackingEvent $a, TrackingEvent $b): int => $a->occurredAt <=> $b->occurredAt);

        return $events;
    }

    /**
     * A scan of the tracking API or the webhook "scans" list: {date, status, activity,
     * location, sr-status, sr-status-label}. Null when it has no date.
     *
     * @param  array<string, mixed>  $scan
     */
    public static function eventFromScan(array $scan): ?TrackingEvent
    {
        $date = $scan['date'] ?? null;

        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        $srLabel = $scan['sr-status-label'] ?? null;
        $srCode = $scan['sr-status'] ?? null;
        $label = is_string($srLabel) && $srLabel !== '' && strtoupper($srLabel) !== 'NA' ? $srLabel : (string) ($scan['status'] ?? $scan['activity'] ?? '');
        $status = ShiprocketStatusMap::map($label, is_numeric($srCode) ? (int) $srCode : null);

        if ($status === null) {
            // Unknown courier wording (plan M13 §5.4): logged, kept as a scan without a transition.
            Log::channel('integrations')->warning('shiprocket unknown tracking status', ['label' => $label, 'code' => $srCode]);
        }

        return new TrackingEvent(
            $status,
            mb_substr($label !== '' ? $label : 'UNKNOWN', 0, 60),
            Carbon::parse($date),
            is_scalar($srCode) ? mb_substr((string) $srCode, 0, 20) : '',
            is_string($scan['location'] ?? null) ? mb_substr($scan['location'], 0, 120) : null,
            is_string($scan['activity'] ?? null) ? mb_substr($scan['activity'], 0, 255) : null,
            $scan,
        );
    }

    public function cancel(Shipment $shipment): bool
    {
        if ($shipment->provider_order_id === null) {
            return true;
        }

        $this->client->post('/v1/external/orders/cancel', ['ids' => [(int) $shipment->provider_order_id]]);

        return true;
    }

    public function createReturn(Shipment $reverse): ProviderShipment
    {
        $reverse->loadMissing(['sellerOrder.salesOrder.customer', 'sellerOrder.salesOrder.addresses', 'sellerOrder.items', 'sellerOrder.warehouse', 'items']);
        $sellerOrder = $reverse->sellerOrder;
        $order = $sellerOrder->salesOrder;
        $customer = $order->addresses->firstWhere('type', SalesOrderAddress::TYPE_SHIPPING);
        $warehouse = $sellerOrder->warehouse;

        if ($customer === null || $warehouse === null) {
            throw new CourierException(trans('logistics::logistics.errors.no_shipping_address'), 'invalid_order');
        }

        $quantities = $reverse->items->pluck('quantity', 'sales_order_item_id');
        $items = $sellerOrder->items->filter(fn (SalesOrderItem $item): bool => $quantities->has($item->id));

        $body = $this->client->post('/v1/external/orders/create/return', [
            'order_id' => $sellerOrder->sub_order_no.'-R'.$reverse->id,
            'order_date' => Carbon::now()->format('Y-m-d'),
            'pickup_customer_name' => $this->firstName($customer->name),
            'pickup_last_name' => $this->lastName($customer->name),
            'pickup_address' => $customer->address_line1,
            'pickup_address_2' => (string) $customer->address_line2,
            'pickup_city' => (string) $customer->city_name,
            'pickup_state' => (string) $customer->state_name,
            'pickup_country' => config('logistics.shiprocket.country', 'India'),
            'pickup_pincode' => $customer->pincode,
            'pickup_email' => $order->customer?->email ?: config('mail.from.address'),
            'pickup_phone' => $this->phone($customer->phone),
            'shipping_customer_name' => $warehouse->contact_name,
            'shipping_address' => $warehouse->address_line1,
            'shipping_address_2' => (string) $warehouse->address_line2,
            'shipping_city' => (string) $warehouse->city_name,
            'shipping_state' => (string) $warehouse->state_name,
            'shipping_country' => config('logistics.shiprocket.country', 'India'),
            'shipping_pincode' => $warehouse->pincode,
            'shipping_phone' => $this->phone($warehouse->contact_phone),
            'order_items' => $items->map(fn (SalesOrderItem $item): array => [
                'name' => Str::limit($item->name, 190, ''),
                'sku' => $item->sku,
                'units' => (int) $quantities->get($item->id),
                'selling_price' => $item->price->toDecimal(),
            ])->values()->all(),
            'payment_method' => 'Prepaid',
            'sub_total' => $items->reduce(fn (Money $sum, SalesOrderItem $item): Money => $sum->plus($item->price->times((int) $quantities->get($item->id))), Money::zero())->toDecimal(),
            'length' => (float) ($reverse->length_cm ?? config('logistics.package.default_length_cm')),
            'breadth' => (float) ($reverse->breadth_cm ?? config('logistics.package.default_breadth_cm')),
            'height' => (float) ($reverse->height_cm ?? config('logistics.package.default_height_cm')),
            'weight' => $this->kilograms($reverse->weight_grams),
        ]);

        if (! isset($body['order_id'])) {
            throw new CourierException(trans('logistics::logistics.errors.provider', ['message' => (string) ($body['message'] ?? 'return not created')]));
        }

        return new ProviderShipment((string) $body['order_id'], isset($body['shipment_id']) ? (string) $body['shipment_id'] : null);
    }

    public function syncPickupLocation(SellerWarehouse $warehouse): string
    {
        $warehouse->loadMissing('seller');
        $code = $warehouse->courier_pickup_ref ?: self::pickupLocationName($warehouse);

        $this->client->post('/v1/external/settings/company/addpickup', [
            'pickup_location' => $code,
            'name' => $warehouse->contact_name,
            'email' => $warehouse->seller?->email ?: config('mail.from.address'),
            'phone' => $this->phone($warehouse->contact_phone),
            'address' => Str::limit($warehouse->address_line1, 80, ''),
            'address_2' => trim(($warehouse->address_line2 ?? '').' '.($warehouse->landmark ?? '')),
            'city' => (string) $warehouse->city_name,
            'state' => (string) $warehouse->state_name,
            'country' => config('logistics.shiprocket.country', 'India'),
            'pin_code' => $warehouse->pincode,
        ]);

        return $code;
    }

    public function ndrAction(Shipment $shipment, string $action, array $payload = []): bool
    {
        if (! $shipment->isBooked()) {
            throw new CourierException(trans('logistics::logistics.errors.not_booked'), 'not_booked');
        }

        $data = ['action' => $action === 'return' ? 'return' : 're-attempt', 'comments' => (string) ($payload['comments'] ?? '')];

        foreach (['phone', 'address1', 'address2'] as $field) {
            if (isset($payload[$field]) && $payload[$field] !== '') {
                $data[$field] = (string) $payload[$field];
            }
        }

        $this->client->post('/v1/external/ndr/'.rawurlencode((string) $shipment->awb).'/action', $data);

        return true;
    }

    /**
     * The Shiprocket pickup location name of a warehouse (unique, at most 36 characters).
     */
    public static function pickupLocationName(SellerWarehouse $warehouse): string
    {
        $warehouse->loadMissing('seller');

        return Str::limit('WH-'.Str::upper((string) ($warehouse->seller?->code ?? $warehouse->seller_id)).'-'.$warehouse->id, 36, '');
    }

    protected function providerShipmentId(Shipment $shipment): string
    {
        if ($shipment->provider_shipment_id === null || $shipment->provider_shipment_id === '') {
            throw new CourierException(trans('logistics::logistics.errors.not_created'), 'not_created');
        }

        return $shipment->provider_shipment_id;
    }

    /**
     * @param  list<Shipment>  $shipments
     * @return list<int>
     */
    protected function providerShipmentIds(array $shipments): array
    {
        return array_values(array_map(fn (Shipment $shipment): int => (int) $this->providerShipmentId($shipment), $shipments));
    }

    protected function kilograms(int $grams): float
    {
        return round(max(1, $grams) / 1000, 3);
    }

    protected function firstName(string $name): string
    {
        return Str::before(trim($name), ' ') ?: trim($name);
    }

    protected function lastName(string $name): string
    {
        $name = trim($name);

        return str_contains($name, ' ') ? trim(Str::after($name, ' ')) : '';
    }

    /**
     * Ten digits for the courier (drops +91 / 0 prefixes).
     */
    protected function phone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
