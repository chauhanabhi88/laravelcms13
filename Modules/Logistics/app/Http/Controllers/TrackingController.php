<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Support\TimelineBuilder;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;

/**
 * Public tracking page (plan M13 §6 tracking.show): /track/{awbOrOrderNo} by AWB, sub-order
 * number or order number. Shows only safe fields (status, steps, courier, AWB, EDD, scan
 * locations): no names, addresses, phones or amounts. Throttled.
 */
class TrackingController extends Controller
{
    public function __construct(private readonly TimelineBuilder $timelines) {}

    public function index(Request $request): View|RedirectResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query !== '') {
            return redirect(frontRoute('tracking.show', ['awbOrOrderNo' => $query]));
        }

        return view('logistics::frontend.tracking.index');
    }

    public function show(Request $request): View
    {
        $reference = trim((string) $request->route('awbOrOrderNo'));
        $sellerOrders = $reference !== '' && mb_strlen($reference) <= 40 ? $this->find($reference) : collect();

        if ($sellerOrders->isEmpty()) {
            abort(404);
        }

        return view('logistics::frontend.tracking.show', [
            'reference' => $reference,
            'timelines' => $sellerOrders->map(fn (SellerOrder $sellerOrder): array => $this->timelines->build($sellerOrder))->all(),
        ]);
    }

    /**
     * @return Collection<int, SellerOrder>
     */
    protected function find(string $reference): Collection
    {
        $shipment = Shipment::query()->where('awb', $reference)->first();

        if ($shipment !== null) {
            return SellerOrder::query()->whereKey($shipment->seller_order_id)->with('salesOrder')->get();
        }

        $sellerOrders = SellerOrder::query()->where('sub_order_no', $reference)->with('salesOrder')->get();

        if ($sellerOrders->isNotEmpty()) {
            return $sellerOrders;
        }

        $orderId = SalesOrder::query()->where('order_no', $reference)->value('id');

        return $orderId === null ? collect() : SellerOrder::query()->where('sales_order_id', $orderId)->with('salesOrder')->orderBy('id')->get();
    }
}
