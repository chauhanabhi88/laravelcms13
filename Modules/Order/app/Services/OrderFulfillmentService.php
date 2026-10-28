<?php

declare(strict_types=1);

namespace Modules\Order\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Notification\Services\NotificationHub;
use Modules\Order\Contracts\FulfilmentLogistics;
use Modules\Order\DTO\FulfilmentResult;
use Modules\Order\DTO\ManifestOutcome;
use Modules\Order\DTO\ProviderLabel;
use Modules\Order\Events\ReturnReceiptConfirmed;
use Modules\Order\Events\SellerPenaltyIncurred;
use Modules\Order\Exceptions\OrderException;
use Modules\Order\Jobs\GenerateLabelBatch;
use Modules\Order\Jobs\RetryShipmentBooking;
use Modules\Order\Models\LabelBatch;
use Modules\Order\Models\SalesOrderDocument;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Support\OrderSettings;
use Modules\Order\Support\Pdf\PackingSlipView;
use Modules\Order\Support\Pdf\PdfRenderer;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;
use Throwable;
use ZipArchive;

/**
 * The supplier order desk's write side (requirement.md OrderFulfillmentService, plan M14 §5):
 * accept (books the courier through FulfilmentLogistics), seller cancel, label batches
 * (courier labels + our packing slips and tax invoices), mark packed, manifests, the SLA and
 * auto-accept jobs and RTO receipts. Status changes go through OrderStatusService /
 * OrderCancellationService; bulk actions handle each seller order on its own.
 */
class OrderFulfillmentService
{
    public function __construct(
        private readonly FulfilmentLogistics $logistics,
        private readonly OrderStatusService $statuses,
        private readonly OrderCancellationService $cancellations,
        private readonly PdfRenderer $pdf,
        private readonly PackingSlipView $views,
        private readonly NotificationHub $hub,
    ) {}

    /**
     * FR-FUL-002: accept seller orders in pending_acceptance (stock still reserved) and book
     * their shipments. A booked order is ready_to_ship with its invoice number; a courier
     * failure keeps it accepted ("AWB pending") and queues a retry.
     *
     * @param  SellerOrder|iterable<SellerOrder>  $sellerOrders
     */
    public function accept(SellerOrder|iterable $sellerOrders, ?Model $actor = null): FulfilmentResult
    {
        $result = new FulfilmentResult;

        foreach ($sellerOrders instanceof SellerOrder ? [$sellerOrders] : $sellerOrders as $sellerOrder) {
            try {
                $accepted = $this->markAccepted($sellerOrder);
            } catch (OrderException $e) {
                $result->add($sellerOrder->id, $sellerOrder->sub_order_no, FulfilmentResult::FAILED, $e->getMessage());

                continue;
            }

            $this->book($accepted, $actor, $result);
        }

        return $result;
    }

    /**
     * A queued retry of an accepted order's AWB booking (RetryShipmentBooking): books again,
     * or counts the failure and queues the next attempt; after the last one Logistics Ops are
     * alerted.
     */
    public function retryBooking(int $sellerOrderId): void
    {
        $sellerOrder = SellerOrder::query()->find($sellerOrderId);

        if ($sellerOrder === null || ! $sellerOrder->isAwaitingAwb()) {
            return;
        }

        $this->book($sellerOrder, null, new FulfilmentResult);
    }

    /**
     * Seller cancellation with a reason (plan M14 §5.3), before pickup: stock is released and a
     * prepaid payment refunded (OrderCancellationService, by = seller), then the penalty hook.
     *
     * @throws OrderException
     */
    public function sellerCancel(SellerOrder $sellerOrder, string $reasonCode, ?string $note = null, ?Model $actor = null): void
    {
        if (! in_array($reasonCode, (array) config('order.fulfilment.seller_cancel_reasons'), true)) {
            throw new OrderException(trans('order::fulfilment.errors.cancel_reason'), 'invalid_reason');
        }

        $reason = trans('order::fulfilment.cancel_reasons.'.$reasonCode);
        $note = $note !== null ? trim($note) : '';

        if ($note !== '') {
            $reason .= ': '.$note;
        }

        if (! $this->cancellations->canCancel($sellerOrder->refresh(), OrderCancellationService::BY_SELLER)) {
            throw OrderException::notCancellable();
        }

        $this->cancellations->cancelItems($sellerOrder, null, OrderCancellationService::BY_SELLER, $reason, $actor);

        SellerPenaltyIncurred::dispatch($sellerOrder->refresh(), SellerPenaltyIncurred::KIND_SELLER_CANCEL, $reason);
    }

    /**
     * FR-FUL-006: ready_to_ship → packed, only once a label was printed.
     *
     * @param  iterable<SellerOrder>  $sellerOrders
     */
    public function markPacked(iterable $sellerOrders, ?Model $actor = null): FulfilmentResult
    {
        $result = new FulfilmentResult;

        foreach ($sellerOrders as $sellerOrder) {
            $sellerOrder->refresh();

            if ($sellerOrder->status === SellerOrder::STATUS_PACKED) {
                $result->add($sellerOrder->id, $sellerOrder->sub_order_no, FulfilmentResult::OK);

                continue;
            }

            if ($sellerOrder->status !== SellerOrder::STATUS_READY_TO_SHIP) {
                $result->add($sellerOrder->id, $sellerOrder->sub_order_no, FulfilmentResult::FAILED, trans('order::fulfilment.errors.not_ready'));

                continue;
            }

            if (! $sellerOrder->hasLabel()) {
                $result->add($sellerOrder->id, $sellerOrder->sub_order_no, FulfilmentResult::FAILED, trans('order::fulfilment.errors.label_first'));

                continue;
            }

            try {
                $this->statuses->transition($sellerOrder, SellerOrder::STATUS_PACKED, trans('order::fulfilment.history.packed'), $actor);
                $result->add($sellerOrder->id, $sellerOrder->sub_order_no, FulfilmentResult::OK);
            } catch (InvalidStateTransition $e) {
                $result->add($sellerOrder->id, $sellerOrder->sub_order_no, FulfilmentResult::FAILED, $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * FR-FUL-004: queue a label batch for booked orders in Ready to Ship (at most
     * order.fulfilment.batch_limit). GenerateLabelBatch builds the file.
     *
     * @param  iterable<SellerOrder>  $sellerOrders
     *
     * @throws OrderException when none of them can get a label
     */
    public function generateLabels(Seller $seller, iterable $sellerOrders, ?Model $actor = null): LabelBatch
    {
        $eligible = [];

        foreach ($sellerOrders as $sellerOrder) {
            if ($sellerOrder->seller_id === $seller->id && in_array($sellerOrder->status, [SellerOrder::STATUS_READY_TO_SHIP, SellerOrder::STATUS_PACKED], true)) {
                $eligible[] = $sellerOrder->id;
            }
        }

        if ($eligible === []) {
            throw new OrderException(trans('order::fulfilment.errors.nothing_to_label'), 'nothing_to_label');
        }

        if (count($eligible) > $this->batchLimit()) {
            throw new OrderException(trans('order::fulfilment.errors.batch_limit', ['limit' => $this->batchLimit()]), 'batch_limit');
        }

        $batch = LabelBatch::query()->create([
            'seller_id' => $seller->id,
            'created_by' => $actor?->getKey(),
            'status' => LabelBatch::STATUS_QUEUED,
            'seller_order_ids' => $eligible,
            'orders_count' => count($eligible),
            'label_format' => in_array($seller->label_format, LabelBatch::FORMATS, true) ? $seller->label_format : OrderSettings::labelFormat(),
        ]);

        GenerateLabelBatch::dispatch($batch->id);

        return $batch;
    }

    /**
     * Build a queued label batch: the courier labels (one call per courier), our packing slips
     * with tax invoices in one PDF, and the tax invoice per order as its own document. With
     * courier label PDFs the download is a ZIP of all files (no PDF merge dependency).
     */
    public function buildLabelBatch(LabelBatch $batch): LabelBatch
    {
        if (! $batch->isPending()) {
            return $batch;
        }

        $batch->forceFill(['status' => LabelBatch::STATUS_PROCESSING, 'error' => null])->save();

        try {
            $sellerOrders = SellerOrder::query()
                ->forSeller($batch->seller_id)
                ->whereKey($batch->seller_order_ids)
                ->whereIn('status', [SellerOrder::STATUS_READY_TO_SHIP, SellerOrder::STATUS_PACKED])
                ->orderBy('id')
                ->get();

            if ($sellerOrders->isEmpty()) {
                throw new OrderException(trans('order::fulfilment.errors.nothing_to_label'), 'nothing_to_label');
            }

            $actor = $batch->created_by !== null ? SellerUser::query()->find($batch->created_by) : null;
            $labels = $this->logistics->labels($sellerOrders->all(), $actor);
            $shipments = $this->logistics->shipments($sellerOrders->modelKeys());
            $now = Carbon::now();

            $documents = [];

            foreach ($sellerOrders as $sellerOrder) {
                $slip = $this->views->packingSlip($sellerOrder, $shipments[$sellerOrder->id] ?? null);
                $invoice = OrderSettings::includeOwnInvoice() && ! $slip['whiteLabel'] ? $this->views->taxInvoice($sellerOrder) : null;
                $documents[] = ['slip' => $slip, 'invoice' => $invoice];
            }

            $disk = $this->disk();
            $folder = $this->folder($batch->seller_id).'/labels';
            $stamp = $now->format('Ymd-His');
            $slipsPath = $folder.'/packing-slips-'.$batch->id.'-'.$stamp.'.pdf';
            $disk->put($slipsPath, $this->pdf->render('order::pdf.packing-slip', ['documents' => $documents], $batch->label_format));

            [$path, $fileName] = $this->packageBatch($batch, $slipsPath, $labels, $stamp);

            foreach ($sellerOrders as $sellerOrder) {
                $this->storeTaxInvoice($sellerOrder);
            }

            DB::transaction(function () use ($batch, $sellerOrders, $labels, $slipsPath, $path, $fileName, $now): void {
                foreach ($sellerOrders as $sellerOrder) {
                    SalesOrderDocument::query()->create([
                        'seller_order_id' => $sellerOrder->id,
                        'label_batch_id' => $batch->id,
                        'type' => SalesOrderDocument::TYPE_PACKING_SLIP,
                        'path' => $slipsPath,
                        'number' => $sellerOrder->sub_order_no,
                        'generated_at' => $now,
                    ]);

                    foreach ($labels as $label) {
                        if (in_array($sellerOrder->id, $label->sellerOrderIds, true)) {
                            SalesOrderDocument::query()->create([
                                'seller_order_id' => $sellerOrder->id,
                                'label_batch_id' => $batch->id,
                                'type' => SalesOrderDocument::TYPE_LABEL,
                                'url' => $label->url,
                                'generated_at' => $now,
                            ]);
                        }
                    }

                    if ($sellerOrder->label_generated_at === null) {
                        SellerOrder::query()->whereKey($sellerOrder->id)->update(['label_generated_at' => $now]);
                    }
                }

                $batch->forceFill([
                    'status' => LabelBatch::STATUS_READY,
                    'path' => $path,
                    'file_name' => $fileName,
                    'orders_count' => $sellerOrders->count(),
                    'provider_labels' => array_map(fn (ProviderLabel $label): array => ['courier' => $label->courier, 'url' => (string) $label->url], array_values(array_filter($labels, fn (ProviderLabel $label): bool => $label->url !== null))),
                    'generated_at' => $now,
                ])->save();
            });

            $this->notifyLabelsReady($batch);
        } catch (Throwable $e) {
            $batch->forceFill(['status' => LabelBatch::STATUS_FAILED, 'error' => mb_substr($e->getMessage(), 0, 500)])->save();

            if (! $e instanceof OrderException) {
                report($e);
            }
        }

        return $batch->refresh();
    }

    /**
     * FR-FUL-005: a pickup batch (manifest + courier pickup) for packed orders of one warehouse.
     *
     * @param  iterable<SellerOrder>  $sellerOrders
     *
     * @throws OrderException
     */
    public function createManifest(iterable $sellerOrders, Carbon $pickupDate, ?Model $actor = null): ManifestOutcome
    {
        $list = [];

        foreach ($sellerOrders as $sellerOrder) {
            $sellerOrder->refresh();

            if ($sellerOrder->status !== SellerOrder::STATUS_PACKED) {
                throw new OrderException(trans('order::fulfilment.errors.manifest_packed_only', ['order' => $sellerOrder->sub_order_no]), 'not_packed');
            }

            $list[] = $sellerOrder;
        }

        if ($list === []) {
            throw new OrderException(trans('order::fulfilment.errors.nothing_selected'), 'empty');
        }

        if (count($list) > $this->batchLimit()) {
            throw new OrderException(trans('order::fulfilment.errors.batch_limit', ['limit' => $this->batchLimit()]), 'batch_limit');
        }

        if (count(array_unique(array_map(fn (SellerOrder $sellerOrder): string => $sellerOrder->seller_id.':'.$sellerOrder->warehouse_id, $list))) !== 1) {
            throw new OrderException(trans('order::fulfilment.errors.one_warehouse'), 'mixed_warehouses');
        }

        if ($pickupDate->copy()->startOfDay()->lessThan(Carbon::today())) {
            throw new OrderException(trans('order::fulfilment.errors.pickup_date_past'), 'pickup_date');
        }

        return $this->logistics->createManifest($list, $pickupDate, $actor);
    }

    /**
     * The seller confirms an RTO package (plan M14 §5.7): good → restocked; damaged or wrong
     * item → kept out of stock, and the note and photos go to the claim (M15 via
     * ReturnReceiptConfirmed).
     *
     * @param  list<UploadedFile>  $photos
     *
     * @throws OrderException
     */
    public function confirmReturnReceipt(SellerOrder $sellerOrder, string $condition, ?string $note = null, array $photos = [], ?Model $actor = null, bool $automatic = false): SellerOrder
    {
        if (! in_array($condition, (array) config('order.fulfilment.receipt_conditions'), true)) {
            throw new OrderException(trans('order::fulfilment.errors.receipt_condition'), 'invalid_condition');
        }

        $sellerOrder->refresh();

        if (in_array($sellerOrder->status, [SellerOrder::STATUS_RETURN_REQUESTED, SellerOrder::STATUS_RETURNED], true)) {
            throw new OrderException(trans('order::fulfilment.errors.customer_returns'), 'customer_return');
        }

        if (! $sellerOrder->awaitsReceipt()) {
            throw new OrderException(trans('order::fulfilment.errors.not_receivable'), 'invalid_state');
        }

        $restock = $condition === 'good';
        $this->logistics->confirmRtoReceipt($sellerOrder, $restock, $actor);

        $paths = [];

        foreach (array_slice($photos, 0, (int) config('order.fulfilment.claim_max_photos', 5)) as $photo) {
            $paths[] = (string) $photo->storeAs(
                $this->folder($sellerOrder->seller_id).'/claims/'.$sellerOrder->sub_order_no,
                Str::random(32).'.'.($photo->guessExtension() ?: 'jpg'),
                (string) config('order.fulfilment.documents_disk', 'private'),
            );
        }

        $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null;

        $sellerOrder->forceFill([
            'receipt_condition' => $condition,
            'receipt_confirmed_at' => Carbon::now(),
            'receipt_confirmed_by' => $automatic ? 'system' : 'seller',
            'receipt_note' => $note,
            'receipt_photos' => $paths === [] ? null : $paths,
        ])->save();

        ReturnReceiptConfirmed::dispatch($sellerOrder, $condition, $restock, $note, $paths, $automatic);

        return $sellerOrder;
    }

    /**
     * Auto-accept (every 10 min): sellers who opted in, orders not yet accepted.
     */
    public function autoAccept(int $limit = 200): FulfilmentResult
    {
        $sellerOrders = SellerOrder::query()
            ->where('status', SellerOrder::STATUS_PENDING_ACCEPTANCE)
            ->whereNull('accepted_at')
            ->whereIn('seller_id', fn ($query) => $query->select('id')->from('seller')
                ->where('auto_accept_orders', (int) config('core.yes'))
                ->where('status', 'active')
                ->whereNull('deleted_at'))
            ->orderBy('sla_dispatch_by')
            ->limit($limit)
            ->get();

        return $this->accept($sellerOrders);
    }

    /**
     * FR-FUL-003: one warning per order not yet packed once the configured share (75%) of its
     * dispatch window has passed.
     */
    public function warnSlaBreaches(?Carbon $now = null, int $limit = 500): int
    {
        $now ??= Carbon::now();
        $share = max(1, min(100, (int) config('order.fulfilment.sla_warning_percent', 75))) / 100;

        $sellerOrders = SellerOrder::query()
            ->whereIn('status', [SellerOrder::STATUS_PENDING_ACCEPTANCE, SellerOrder::STATUS_READY_TO_SHIP])
            ->whereNull('sla_warned_at')
            ->whereNotNull('sla_dispatch_by')
            ->whereRaw('DATE_ADD(created_at, INTERVAL FLOOR(TIMESTAMPDIFF(SECOND, created_at, sla_dispatch_by) * ?) SECOND) <= ?', [$share, $now->toDateTimeString()])
            ->with('seller')
            ->orderBy('sla_dispatch_by')
            ->limit($limit)
            ->get();

        $sent = 0;

        foreach ($sellerOrders as $sellerOrder) {
            // Claim the warning first, so a parallel run never sends it twice.
            if (SellerOrder::query()->whereKey($sellerOrder->id)->whereNull('sla_warned_at')->update(['sla_warned_at' => $now]) !== 1) {
                continue;
            }

            try {
                $this->hub->eventForSeller('seller.sla.warning', $sellerOrder->seller, [
                    'order_no' => $sellerOrder->sub_order_no,
                    'dispatch_by' => $sellerOrder->sla_dispatch_by?->translatedFormat('D, j M, g:i A') ?? '',
                    'url' => frontRoute('seller.order.show', [$sellerOrder->sub_order_no]),
                ]);
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    /**
     * FR-FUL-003: cancel orders not handed over by their dispatch deadline plus the grace
     * period (by = system): stock released, prepaid refunded, customer and seller told, penalty
     * hook fired.
     */
    public function cancelSlaBreached(?Carbon $now = null, int $limit = 200): int
    {
        $cutoff = ($now ?? Carbon::now())->copy()->subHours(OrderSettings::slaGraceHours());
        $reason = trans('order::fulfilment.sla.cancel_reason');
        $cancelled = 0;

        $sellerOrders = SellerOrder::query()
            ->whereIn('status', [SellerOrder::STATUS_PENDING_ACCEPTANCE, SellerOrder::STATUS_READY_TO_SHIP, SellerOrder::STATUS_PACKED])
            ->where('sla_dispatch_by', '<', $cutoff)
            ->with('seller')
            ->orderBy('sla_dispatch_by')
            ->limit($limit)
            ->get();

        foreach ($sellerOrders as $sellerOrder) {
            try {
                $this->cancellations->cancelItems($sellerOrder, null, OrderCancellationService::BY_SYSTEM, $reason);
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            $cancelled++;
            SellerPenaltyIncurred::dispatch($sellerOrder->refresh(), SellerPenaltyIncurred::KIND_SLA_BREACH, $reason);

            try {
                $this->hub->eventForSeller('seller.order.sla_cancelled', $sellerOrder->seller, [
                    'order_no' => $sellerOrder->sub_order_no,
                    'reason' => $reason,
                    'url' => frontRoute('seller.order.show', [$sellerOrder->sub_order_no]),
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $cancelled;
    }

    /**
     * RTO packages not confirmed within the configured days count as received in good
     * condition (restocked).
     */
    public function autoConfirmReceipts(?Carbon $now = null, int $limit = 200): int
    {
        $cutoff = ($now ?? Carbon::now())->copy()->subDays(OrderSettings::autoConfirmReturnDays());
        $confirmed = 0;

        $sellerOrders = SellerOrder::query()
            ->where('status', SellerOrder::STATUS_RTO_DELIVERED)
            ->whereNull('receipt_confirmed_at')
            ->whereHas('statusHistories', fn ($query) => $query->where('to_status', SellerOrder::STATUS_RTO_DELIVERED)->where('created_at', '<=', $cutoff))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($sellerOrders as $sellerOrder) {
            try {
                $this->confirmReturnReceipt($sellerOrder, 'good', trans('order::fulfilment.receipt.auto_note'), [], null, true);
                $confirmed++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $confirmed;
    }

    /**
     * Private-disk path of a document, for streaming to an authorised user.
     */
    public function documentPath(LabelBatch|SalesOrderDocument $document): ?string
    {
        $path = $document->path;

        return $path !== null && $this->disk()->exists($path) ? $path : null;
    }

    public function disk(): Filesystem
    {
        return Storage::disk((string) config('order.fulfilment.documents_disk', 'private'));
    }

    /**
     * Lock the order and stamp its acceptance (stays pending_acceptance until the AWB).
     *
     * @throws OrderException
     */
    protected function markAccepted(SellerOrder $sellerOrder): SellerOrder
    {
        return DB::transaction(function () use ($sellerOrder): SellerOrder {
            $locked = SellerOrder::query()->lockForUpdate()->findOrFail($sellerOrder->id);

            if ($locked->status !== SellerOrder::STATUS_PENDING_ACCEPTANCE) {
                throw new OrderException(trans('order::fulfilment.errors.not_acceptable'), 'not_acceptable');
            }

            $active = SalesOrderItem::query()->where('seller_order_id', $locked->id)->where('status', SalesOrderItem::STATUS_ACTIVE)->exists();

            if (! $active) {
                throw new OrderException(trans('order::fulfilment.errors.no_active_items'), 'no_items');
            }

            if ($locked->accepted_at === null) {
                $locked->forceFill(['accepted_at' => Carbon::now(), 'awb_failures' => 0])->save();
            }

            return $locked;
        });
    }

    protected function book(SellerOrder $sellerOrder, ?Model $actor, FulfilmentResult $result): void
    {
        $outcome = $this->logistics->book($sellerOrder, $actor);
        $sellerOrder->refresh();

        if ($outcome->booked) {
            if ($sellerOrder->status === SellerOrder::STATUS_READY_TO_SHIP && $sellerOrder->invoice_no === null) {
                $this->statuses->assignInvoiceNumber($sellerOrder);
            }

            $result->add($sellerOrder->id, $sellerOrder->sub_order_no, FulfilmentResult::OK);

            return;
        }

        if (! $outcome->retryable) {
            $result->add($sellerOrder->id, $sellerOrder->sub_order_no, FulfilmentResult::FAILED, $outcome->error);

            return;
        }

        $failures = $sellerOrder->awb_failures + 1;
        SellerOrder::query()->whereKey($sellerOrder->id)->update(['awb_failures' => min(255, $failures)]);
        $backoff = array_values((array) config('order.fulfilment.booking_backoff', [300, 900, 1800]));

        if ($failures <= count($backoff)) {
            RetryShipmentBooking::dispatch($sellerOrder->id)->delay(Carbon::now()->addSeconds((int) $backoff[$failures - 1]));
        } else {
            $this->alertBookingFailed($sellerOrder, (string) $outcome->error, $failures);
        }

        $result->add($sellerOrder->id, $sellerOrder->sub_order_no, FulfilmentResult::AWB_PENDING, $outcome->error);
    }

    protected function alertBookingFailed(SellerOrder $sellerOrder, string $error, int $attempts): void
    {
        try {
            $this->hub->eventForAdmins('admin.shipment.attention', [
                'order_no' => $sellerOrder->sub_order_no,
                'reason' => trans('order::fulfilment.errors.booking_gave_up', ['attempts' => $attempts, 'error' => $error]),
                'url' => route('admin.seller_order.show', updateUrlParams([$sellerOrder->id])),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * The batch download: the packing slips PDF alone, or a ZIP with it and the courier label
     * PDFs (downloaded; a link list when a download fails).
     *
     * @param  list<ProviderLabel>  $labels
     * @return array{0: string, 1: string} path and download name
     */
    protected function packageBatch(LabelBatch $batch, string $slipsPath, array $labels, string $stamp): array
    {
        $urls = array_values(array_filter($labels, fn (ProviderLabel $label): bool => $label->url !== null && $label->url !== ''));

        if ($urls === []) {
            return [$slipsPath, 'labels-'.$batch->id.'.pdf'];
        }

        $disk = $this->disk();
        $tmp = tempnam(sys_get_temp_dir(), 'lbl');
        $zip = new ZipArchive;

        if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new OrderException(trans('order::fulfilment.errors.zip_failed'), 'zip_failed');
        }

        $zip->addFromString('packing-slips-and-invoices.pdf', (string) $disk->get($slipsPath));
        $links = [];

        foreach ($urls as $index => $label) {
            $content = $this->download((string) $label->url);
            $name = 'courier-labels-'.($index + 1).'-'.Str::slug($label->courier ?: 'courier').'.pdf';

            if ($content !== null) {
                $zip->addFromString($name, $content);
            } else {
                $links[] = ($label->courier ?: 'Courier').': '.$label->url;
            }
        }

        if ($links !== []) {
            $zip->addFromString('courier-labels.txt', trans('order::fulfilment.labels.links_note')."\n\n".implode("\n", $links)."\n");
        }

        $zip->close();

        $path = $this->folder($batch->seller_id).'/labels/labels-'.$batch->id.'-'.$stamp.'.zip';
        $disk->put($path, (string) file_get_contents($tmp));
        @unlink($tmp);

        return [$path, 'labels-'.$batch->id.'.zip'];
    }

    /**
     * A courier label PDF, or null when it cannot be fetched.
     */
    protected function download(string $url): ?string
    {
        try {
            $response = Http::timeout(15)->retry(2, 500, throw: false)->get($url);
        } catch (Throwable) {
            return null;
        }

        $body = $response->successful() ? $response->body() : '';

        return str_starts_with($body, '%PDF') ? $body : null;
    }

    /**
     * The order's tax invoice as its own PDF document (once per invoice number; M17 reuses it).
     */
    protected function storeTaxInvoice(SellerOrder $sellerOrder): void
    {
        if ($sellerOrder->invoice_no === null) {
            return;
        }

        $exists = SalesOrderDocument::query()
            ->where('seller_order_id', $sellerOrder->id)
            ->where('type', SalesOrderDocument::TYPE_TAX_INVOICE)
            ->where('number', $sellerOrder->invoice_no)
            ->exists();

        if ($exists) {
            return;
        }

        $path = $this->folder($sellerOrder->seller_id).'/invoices/'.Str::slug(str_replace('/', '-', $sellerOrder->invoice_no)).'.pdf';
        $this->disk()->put($path, $this->pdf->render('order::pdf.tax-invoice', ['invoice' => $this->views->taxInvoice($sellerOrder)]));

        SalesOrderDocument::query()->create([
            'seller_order_id' => $sellerOrder->id,
            'type' => SalesOrderDocument::TYPE_TAX_INVOICE,
            'path' => $path,
            'number' => $sellerOrder->invoice_no,
            'generated_at' => Carbon::now(),
        ]);
    }

    protected function notifyLabelsReady(LabelBatch $batch): void
    {
        try {
            $seller = Seller::query()->find($batch->seller_id);

            if ($seller !== null) {
                $this->hub->eventForSeller('seller.labels.ready', $seller, [
                    'count' => $batch->orders_count,
                    'url' => frontRoute('seller.label.download', [$batch->id]),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function folder(int $sellerId): string
    {
        return trim((string) config('order.fulfilment.documents_folder', 'order-documents'), '/').'/'.$sellerId;
    }

    protected function batchLimit(): int
    {
        return max(1, (int) config('order.fulfilment.batch_limit', 100));
    }
}
