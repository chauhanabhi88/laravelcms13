<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;
use Modules\Order\Http\Controllers\Concerns\ResolvesOwnOrder;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\Payment;
use Modules\Returns\DTO\ReturnableItem;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Http\Requests\ReturnPhotosRequest;
use Modules\Returns\Http\Requests\ReturnReasonRequest;
use Modules\Returns\Http\Requests\ReturnRefundRequest;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Models\ReturnRequestImage;
use Modules\Returns\Services\ReturnEligibilityService;
use Modules\Returns\Services\ReturnRefundService;
use Modules\Returns\Services\ReturnService;
use Modules\Returns\Support\ReturnDraft;
use Modules\Returns\Support\ReturnOptions;
use Modules\Returns\Support\ReturnPhotoStore;
use Modules\Returns\Support\ReturnSettings;
use Modules\Returns\Support\ReturnTimeline;
use Modules\Returns\Support\Validation\RefundDetailsRules;
use Modules\Seller\Exceptions\IfscLookupUnavailable;
use Modules\Seller\Support\IfscLookup;
use Symfony\Component\HttpFoundation\Response;

/**
 * The customer's returns (FR-RET-003/004/005, plan M15 §5.3): a three-step wizard kept in the
 * session (items and reason → photos → refund details and pickup address) and a confirmation
 * page that raises the return; the returns list and detail with the timeline; cancel before
 * pickup. Orders and returns of other customers answer 404.
 */
class ReturnController extends Controller
{
    use ResolvesOwnOrder;

    public function __construct(
        private readonly ReturnEligibilityService $eligibility,
        private readonly ReturnService $returns,
        private readonly ReturnPhotoStore $photos,
    ) {}

    public function index(): View
    {
        return view('returns::frontend.account.return.index', [
            'returns' => ReturnRequest::query()
                ->forCustomer($this->customer()->id)
                ->with(['items.orderItem', 'sellerOrder'])
                ->orderByDesc('id')
                ->paginate((int) config('returns.customer_per_page', 10)),
        ]);
    }

    /**
     * Step 1 for a package of the order (?seller_order=, default: the first returnable one).
     */
    public function create(Request $request): View|RedirectResponse
    {
        $customer = $this->customer();
        $order = $this->ownOrder($request, $customer, ['sellerOrders.items', 'sellerOrders.seller']);
        $eligible = $this->eligibility->forOrder($order, $customer);
        $sellerOrderId = (int) $request->query('seller_order', (string) (array_key_first($eligible) ?? 0));

        if (! isset($eligible[$sellerOrderId])) {
            return redirect(frontRoute('customer.orders.show', ['orderNo' => $order->order_no]))->with('error', trans('returns::front.errors.not_eligible'));
        }

        $draft = ReturnDraft::fromRequest($request);

        if ($draft === null || $draft->sellerOrderId !== $sellerOrderId || $draft->orderNo !== $order->order_no) {
            $this->discardDraftPhotos($draft, $customer);
            $draft = new ReturnDraft($order->order_no, $sellerOrderId);
            $draft->save($request);
        }

        /** @var SellerOrder $sellerOrder */
        $sellerOrder = $order->sellerOrders->firstWhere('id', $sellerOrderId);

        return view('returns::frontend.account.return.create-reason', $this->wizardData($order, $sellerOrder, $eligible[$sellerOrderId], $draft));
    }

    public function step(Request $request): View|RedirectResponse
    {
        $step = (string) $request->route('step');
        [$draft, $order, $sellerOrder, $items] = $this->context($request);

        if ($draft === null || ! $draft->reasonDone() || ($step === 'refund' && ! $draft->photosDone) || ($step === 'confirm' && ! $draft->refundDone)) {
            return $this->restart($order, $draft);
        }

        $data = $this->wizardData($order, $sellerOrder, $items, $draft);

        return match ($step) {
            'photos' => view('returns::frontend.account.return.create-photos', $data + [
                'photosRequired' => ReturnRequest::photosRequiredFor((string) $draft->reasonCode),
                'maxImages' => ReturnSettings::maxImages(),
            ]),
            'refund' => view('returns::frontend.account.return.create-refund', $data + [
                'isCod' => $order->payment_method === Payment::METHOD_COD,
                'beneficiary' => $draft->beneficiaryDetails() ?? [],
                'addresses' => $this->pickupAddresses($order),
            ]),
            default => view('returns::frontend.account.return.confirm', $data + [
                'isCod' => $order->payment_method === Payment::METHOD_COD,
                'beneficiary' => $draft->beneficiaryDetails(),
                'pickupAddress' => $this->pickupAddressLine($order, $draft->pickupAddressId),
                'quote' => $this->quote($sellerOrder, $draft),
            ]),
        };
    }

    public function storeStep(Request $request, IfscLookup $ifsc): RedirectResponse
    {
        $step = (string) $request->route('step');
        [$draft, $order, $sellerOrder, $items] = $this->context($request);

        if ($draft === null || ($step !== 'reason' && ! $draft->reasonDone()) || ($step === 'refund' && ! $draft->photosDone)) {
            return $this->restart($order, $draft);
        }

        match ($step) {
            'reason' => $this->saveReason(app(ReturnReasonRequest::class), $draft, $items),
            'photos' => $this->savePhotos(app(ReturnPhotosRequest::class), $draft, $request),
            default => $this->saveRefund(app(ReturnRefundRequest::class), $draft, $order, $ifsc),
        };

        $draft->save($request);

        $next = match ($step) {
            'reason' => 'photos',
            'photos' => 'refund',
            default => 'confirm',
        };

        return redirect(frontRoute('customer.returns.step', ['step' => $next]));
    }

    public function submit(Request $request): RedirectResponse
    {
        $customer = $this->customer();
        [$draft, $order] = $this->context($request);

        if ($draft === null || ! $draft->reasonDone() || ! $draft->photosDone || ! $draft->refundDone) {
            return $this->restart($order, $draft);
        }

        try {
            $return = $this->returns->create($customer, $draft->toSubmission());
        } catch (ReturnException $e) {
            $step = match (array_key_first($e->errors)) {
                'photos' => 'photos',
                'refund_mode', 'pickup_address_id' => 'refund',
                default => 'confirm',
            };

            return redirect(frontRoute('customer.returns.step', ['step' => $step]))->with('error', $e->getMessage());
        }

        ReturnDraft::forget($request);

        return redirect(frontRoute('customer.returns.show', ['returnNo' => $return->return_no]))
            ->with('success', trans('returns::front.messages.created', ['return_no' => $return->return_no]));
    }

    public function show(Request $request, ReturnTimeline $timeline): View
    {
        $return = $this->ownReturn($request, ['items.orderItem', 'items.exchangeVariant', 'images', 'sellerOrder.seller', 'salesOrder', 'reverseShipment', 'refunds', 'exchangeSellerOrder']);

        return view('returns::frontend.account.return.show', [
            'return' => $return,
            'events' => $timeline->build($return),
        ]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $return = $this->ownReturn($request);
        $back = redirect(frontRoute('customer.returns.show', ['returnNo' => $return->return_no]));

        try {
            $this->returns->cancel($return, $this->customer(), trans('returns::front.cancel.reason'));
        } catch (ReturnException $e) {
            return $back->with('error', $e->getMessage());
        }

        return $back->with('success', trans('returns::front.messages.cancelled'));
    }

    public function photo(Request $request): Response
    {
        $return = $this->ownReturn($request);
        $image = ReturnRequestImage::query()->where('return_request_id', $return->id)->findOrFail((int) $request->route('image'));

        abort_unless($this->photos->disk()->exists($image->path), 404);

        return $this->photos->disk()->response($image->path);
    }

    public function draftPhoto(Request $request): Response
    {
        $path = $this->photos->draftFolder($this->customer()->id).'/'.$request->route('name');

        abort_unless($this->photos->isDraftOf($path, $this->customer()->id), 404);

        return $this->photos->disk()->response($path);
    }

    /**
     * @param  Collection<int, ReturnableItem>  $items
     */
    protected function saveReason(ReturnReasonRequest $request, ReturnDraft $draft, Collection $items): void
    {
        $type = (string) $request->validated('type');
        $lines = [];

        foreach ((array) $request->validated('items', []) as $itemId => $line) {
            if (empty($line['selected'])) {
                continue;
            }

            $candidate = $items->get((int) $itemId);
            $qty = (int) ($line['quantity'] ?? 1);
            $variantId = isset($line['exchange_variant_id']) ? (int) $line['exchange_variant_id'] : null;

            if ($candidate === null || $qty < 1 || $qty > $candidate->maxQty) {
                throw ValidationException::withMessages(['items' => trans('returns::front.errors.qty_invalid', ['max' => (string) ($candidate?->maxQty ?? 0)])]);
            }

            if (! $candidate->allows($type)) {
                throw ValidationException::withMessages(['type' => trans('returns::front.errors.type_not_allowed')]);
            }

            if ($type === ReturnRequest::TYPE_EXCHANGE && ($variantId === null || ! $candidate->allowsExchangeTo($variantId))) {
                throw ValidationException::withMessages(['items' => trans('returns::front.errors.exchange_variant_invalid')]);
            }

            $lines[(int) $itemId] = ['quantity' => $qty, 'exchange_variant_id' => $type === ReturnRequest::TYPE_EXCHANGE ? $variantId : null];
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['items' => trans('returns::front.errors.items_required')]);
        }

        $changedType = $draft->type !== null && $draft->type !== $type;
        $draft->type = $type;
        $draft->reasonCode = (string) $request->validated('reason_code');
        $draft->comment = $request->validated('comment');
        $draft->items = $lines;
        $draft->photosDone = $draft->photosDone && ! (ReturnRequest::photosRequiredFor($draft->reasonCode) && $draft->photos === []);
        $draft->refundDone = $draft->refundDone && ! $changedType;
    }

    protected function savePhotos(ReturnPhotosRequest $request, ReturnDraft $draft, Request $session): void
    {
        $customerId = $this->customer()->id;
        $remove = array_map('strval', (array) $request->validated('remove', []));
        $kept = [];

        foreach ($draft->photos as $photo) {
            if (in_array(basename($photo['path']), $remove, true)) {
                $this->photos->delete($photo['path']);

                continue;
            }

            $kept[] = $photo;
        }

        // Removed files are gone: keep the draft in step even when this step fails below.
        $draft->photos = $kept;
        $draft->save($session);

        $uploads = array_values(array_filter((array) $request->file('photos', []), fn ($file): bool => $file instanceof UploadedFile));

        if (count($kept) + count($uploads) > ReturnSettings::maxImages()) {
            throw ValidationException::withMessages(['photos' => trans('returns::front.errors.too_many_photos', ['max' => (string) ReturnSettings::maxImages()])]);
        }

        try {
            $stored = $this->photos->storeMany($uploads, 'drafts/'.$customerId);
        } catch (ReturnException $e) {
            throw ValidationException::withMessages($e->errors);
        }

        $draft->photos = array_merge($kept, $stored);

        if ($draft->photos === [] && ReturnRequest::photosRequiredFor((string) $draft->reasonCode)) {
            $draft->photosDone = false;
            $draft->save($session);

            throw ValidationException::withMessages(['photos' => trans('returns::front.errors.photos_required')]);
        }

        $draft->photosDone = true;
    }

    protected function saveRefund(ReturnRefundRequest $request, ReturnDraft $draft, SalesOrder $order, IfscLookup $ifsc): void
    {
        $isCod = $order->payment_method === Payment::METHOD_COD;
        $validated = $request->validated();

        if ($isCod && ($draft->type === ReturnRequest::TYPE_RETURN || filled($validated['refund_mode'] ?? null))) {
            if ($draft->type === ReturnRequest::TYPE_RETURN && blank($validated['refund_mode'] ?? null)) {
                throw ValidationException::withMessages(['refund_mode' => trans('returns::front.errors.refund_details_missing')]);
            }

            $details = RefundDetailsRules::beneficiary($validated);

            if (($details['ifsc'] ?? null) !== null) {
                try {
                    $bank = $ifsc->find($details['ifsc']);

                    if ($bank === null) {
                        throw ValidationException::withMessages(['ifsc' => trans('returns::front.validation.ifsc_unknown')]);
                    }

                    $details['bank'] = $bank['bank'];
                } catch (IfscLookupUnavailable) {
                    // The format was checked; the bank name stays unknown.
                }
            }

            $draft->refundMode = (string) $validated['refund_mode'];
            $draft->setBeneficiary($details);
        } else {
            $draft->refundMode = $isCod ? null : ($draft->type === ReturnRequest::TYPE_RETURN ? 'source' : null);
            $draft->setBeneficiary(null);
        }

        $addressId = isset($validated['pickup_address_id']) ? (int) $validated['pickup_address_id'] : null;

        if ($addressId !== null && ! $this->pickupAddresses($order)->contains('id', $addressId)) {
            throw ValidationException::withMessages(['pickup_address_id' => trans('returns::front.errors.pickup_address_invalid')]);
        }

        $draft->pickupAddressId = $addressId;
        $draft->refundDone = true;
    }

    /**
     * [draft, order, seller order, returnable items] for the current wizard.
     *
     * @return array{0: ReturnDraft|null, 1: SalesOrder|null, 2: SellerOrder|null, 3: Collection<int, ReturnableItem>}
     */
    protected function context(Request $request): array
    {
        $draft = ReturnDraft::fromRequest($request);
        $customer = $this->customer();
        $order = $draft === null ? null : SalesOrder::query()->forCustomer($customer->id)->with('shippingAddress')->where('order_no', $draft->orderNo)->first();
        $sellerOrder = $order === null ? null : SellerOrder::query()->where('sales_order_id', $order->id)->with(['items', 'seller'])->find($draft->sellerOrderId);
        $items = $sellerOrder === null ? collect() : $this->eligibility->eligibleItems($sellerOrder->setRelation('salesOrder', $order), $customer);

        if ($sellerOrder === null || $items->isEmpty()) {
            return [null, $order, null, collect()];
        }

        return [$draft, $order, $sellerOrder, $items];
    }

    protected function restart(?SalesOrder $order, ?ReturnDraft $draft): RedirectResponse
    {
        if ($draft === null || $order === null) {
            return redirect(frontRoute('customer.orders.index'))->with('error', trans('returns::front.errors.draft_expired'));
        }

        return redirect(frontRoute('customer.returns.create', ['orderNo' => $order->order_no, ['seller_order' => $draft->sellerOrderId]]));
    }

    /**
     * @param  Collection<int, ReturnableItem>  $items
     * @return array<string, mixed>
     */
    protected function wizardData(SalesOrder $order, SellerOrder $sellerOrder, Collection $items, ReturnDraft $draft): array
    {
        return [
            'order' => $order,
            'sellerOrder' => $sellerOrder,
            'items' => $items,
            'draft' => $draft,
            'reasons' => ReturnOptions::reasons(),
            'types' => ReturnOptions::types(),
            'photoRequiredReasons' => (array) config('returns.photo_required_reasons', []),
        ];
    }

    /**
     * Saved addresses in the delivery pincode (the courier picks up within it).
     *
     * @return Collection<int, CustomerAddress>
     */
    protected function pickupAddresses(SalesOrder $order): Collection
    {
        $pincode = $order->deliveryPincode();

        return $pincode === null ? collect() : CustomerAddress::query()
            ->where('customer_id', $this->customer()->id)
            ->where('pincode', $pincode)
            ->orderByDesc('id')
            ->get();
    }

    protected function pickupAddressLine(SalesOrder $order, ?int $addressId): string
    {
        $address = $addressId !== null ? $this->pickupAddresses($order)->firstWhere('id', $addressId) : null;

        return $address !== null ? $address->summary() : (string) $order->shippingAddress?->summary();
    }

    /**
     * @return array{items: Money, delivery: Money, total: Money}
     */
    protected function quote(SellerOrder $sellerOrder, ReturnDraft $draft): array
    {
        $quote = app(ReturnRefundService::class)->quote(
            $sellerOrder,
            array_map(fn (array $line): int => $line['quantity'], $draft->items),
            (string) $draft->reasonCode,
        );

        return ['items' => $quote->itemsTotal(), 'delivery' => $quote->delivery, 'total' => $quote->total()];
    }

    protected function discardDraftPhotos(?ReturnDraft $draft, Customer $customer): void
    {
        foreach ($draft?->photos ?? [] as $photo) {
            if ($this->photos->isDraftOf($photo['path'], $customer->id)) {
                $this->photos->delete($photo['path']);
            }
        }
    }

    /**
     * @param  list<string>  $with
     */
    protected function ownReturn(Request $request, array $with = []): ReturnRequest
    {
        $return = ReturnRequest::query()
            ->forCustomer($this->customer()->id)
            ->with($with)
            ->where('return_no', (string) $request->route('returnNo'))
            ->first();

        abort_if($return === null, 404);

        return $return;
    }

    protected function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return $customer;
    }
}
