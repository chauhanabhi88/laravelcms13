<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Controllers\Seller;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Http\Requests\Seller\ReturnQcRequest;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Models\ReturnRequestImage;
use Modules\Returns\Services\ReturnClaimService;
use Modules\Returns\Services\ReturnService;
use Modules\Returns\Support\ReturnPhotoStore;
use Modules\Returns\Support\ReturnTimeline;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Supplier returns (FR-RET-006): returns of the seller's packages by tab (incoming, to check,
 * closed), the detail with the customer's reason and photos, the QC result (good → restock and
 * refund / exchange; bad → CX decides, with an optional claim) and claims within the window.
 */
class SellerReturnController extends SellerPanelController
{
    public const TABS = [
        'to_check' => ReturnRequest::QC_STATUSES,
        'incoming' => [ReturnRequest::STATUS_REQUESTED, ReturnRequest::STATUS_APPROVED, ReturnRequest::STATUS_PICKUP_SCHEDULED],
        'closed' => [
            ReturnRequest::STATUS_QC_PASSED,
            ReturnRequest::STATUS_QC_FAILED,
            ReturnRequest::STATUS_REFUND_INITIATED,
            ReturnRequest::STATUS_REFUNDED,
            ReturnRequest::STATUS_EXCHANGE_ORDER_CREATED,
            ReturnRequest::STATUS_REJECTED,
            ReturnRequest::STATUS_CANCELLED,
            ReturnRequest::STATUS_CLOSED,
        ],
    ];

    public function __construct(
        private readonly ReturnService $returns,
        private readonly ReturnPhotoStore $photos,
    ) {}

    public function index(Request $request): View
    {
        return view('returns::seller.return.index', $this->gridData($request));
    }

    public function filters(Request $request): JsonResponse
    {
        return response()->json([
            'type' => 'success',
            'content' => ['element' => 'collection', 'html' => view('returns::seller.return.partials.grid', $this->gridData($request))->render()],
        ]);
    }

    public function show(Request $request, ReturnTimeline $timeline, ReturnClaimService $claims): View
    {
        $return = $this->ownReturn($request, ['items.orderItem', 'items.exchangeVariant', 'images', 'sellerOrder', 'reverseShipment', 'claims']);

        return view('returns::seller.return.show', [
            'return' => $return,
            'events' => $timeline->build($return),
            'canClaim' => $claims->canClaim($return),
        ]);
    }

    public function qc(ReturnQcRequest $request): RedirectResponse
    {
        $return = $request->returnRequest();
        $back = redirect(frontRoute('seller.return.show', ['returnNo' => $return->return_no]));

        try {
            if ($request->passed()) {
                $this->returns->qcPass($return, $request->validated('note'), $this->sellerUser());
            } else {
                $this->returns->qcFail($return, (string) $request->validated('note'), $this->sellerUser(), $request->claim());
            }
        } catch (ReturnException $e) {
            return $back->with('error', $e->getMessage());
        }

        return $back->with('success', trans($request->passed() ? 'returns::seller.messages.qc_passed' : 'returns::seller.messages.qc_failed', ['return_no' => $return->return_no]));
    }

    public function photo(Request $request): Response
    {
        $return = $this->ownReturn($request);
        $image = ReturnRequestImage::query()->where('return_request_id', $return->id)->findOrFail((int) $request->route('image'));

        abort_unless($this->photos->disk()->exists($image->path), 404);

        return $this->photos->disk()->response($image->path);
    }

    /**
     * @param  list<string>  $with
     */
    protected function ownReturn(Request $request, array $with = []): ReturnRequest
    {
        $return = ReturnRequest::query()->with($with)->where('return_no', (string) $request->route('returnNo'))->firstOrFail();
        $this->authorizeOwned($return);

        return $return;
    }

    /**
     * @return array<string, mixed>
     */
    protected function gridData(Request $request): array
    {
        $tab = array_key_exists((string) $request->input('tab'), self::TABS) ? (string) $request->input('tab') : 'to_check';
        $search = trim((string) $request->input('search', ''));
        $sellerId = $this->seller()->id;

        /** @var LengthAwarePaginator<int, ReturnRequest> $returns */
        $returns = ReturnRequest::query()
            ->forSeller($sellerId)
            ->whereIn('status', self::TABS[$tab])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('return_no', 'like', '%'.$search.'%')
                ->orWhereHas('sellerOrder', fn ($order) => $order->where('sub_order_no', 'like', '%'.$search.'%'))))
            ->with(['items.orderItem', 'sellerOrder'])
            ->orderByDesc('id')
            ->paginate((int) config('returns.seller_per_page', 25))
            ->withQueryString();

        $counts = ReturnRequest::query()->forSeller($sellerId)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'returns' => $returns,
            'tab' => $tab,
            'search' => $search,
            'counts' => collect(self::TABS)->map(fn (array $statuses): int => (int) $counts->only($statuses)->sum())->all(),
        ];
    }
}
