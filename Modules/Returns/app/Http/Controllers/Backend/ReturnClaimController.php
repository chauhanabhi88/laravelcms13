<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Core\Support\Money;
use Modules\Order\Services\OrderFulfillmentService;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Repositories\ReturnClaimRepository;
use Modules\Returns\Services\ReturnClaimService;
use Modules\Returns\Support\ReturnOptions;
use Modules\Returns\Support\ReturnPhotoStore;
use Symfony\Component\HttpFoundation\Response;

/**
 * Seller claims queue (FR-RET-006): damaged / wrong / used / incomplete returns and RTO
 * packages. Accept with the amount credited to the seller (M17 claim_credit), or reject.
 */
class ReturnClaimController extends BackendController
{
    public function __construct(private ReturnClaimRepository $claimRepository)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('returns.cache.return_claim'), $request->input('per_page'))]);

            return view('returns::backend.return_claim.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('returns.cache.return_claim'), $request->input('per_page'))]);
            setFilterSession(config('returns.cache.return_claim'), $request);

            $content = view('returns::backend.return_claim.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.return_claim.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(Request $request): View|RedirectResponse
    {
        try {
            $claim = $this->findClaim($request);
            $claim->load(['returnRequest.items.orderItem', 'sellerOrder.salesOrder', 'seller']);

            return view('returns::backend.return_claim.show', [
                'claim' => $claim,
                'statuses' => ReturnOptions::claimStatuses(),
                'reasons' => ReturnOptions::claimReasons(),
            ]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.return_claim.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function photo(Request $request, ReturnPhotoStore $photos): Response
    {
        $claim = ReturnClaim::query()->findOrFail((int) $request->route('id'));
        $path = ((array) $claim->images)[(int) $request->route('index')] ?? null;
        $disk = $claim->source === ReturnClaim::SOURCE_RTO ? app(OrderFulfillmentService::class)->disk() : $photos->disk();

        abort_unless(is_string($path) && $disk->exists($path), 404);

        return $disk->response($path);
    }

    public function decide(Request $request, ReturnClaimService $claims): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'resolution_amount' => ['nullable', 'required_if:decision,accept', 'numeric', 'min:0.01', 'max:99999999'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $claim = $this->findClaim($request);
            $accept = $validated['decision'] === 'accept';
            $claims->decide($claim, $accept, $accept ? Money::of((string) $validated['resolution_amount']) : null, $validated['note'] ?? null, $request->user());

            return redirect()->route('admin.return_claim.show', updateUrlParams([$claim->id]))
                ->with('success', trans('returns::return_claim.messages.'.($accept ? 'accepted' : 'rejected')));
        } catch (ReturnException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage())->withErrors($e->errors);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function findClaim(Request $request): ReturnClaim
    {
        $claim = $this->claimRepository->find((int) $request->route('id'));

        if (! $claim instanceof ReturnClaim) {
            throw new \RuntimeException(trans('returns::return_claim.messages.not_found'));
        }

        return $claim;
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = [
            'statuses' => ReturnOptions::claimStatuses(),
            'reasons' => ReturnOptions::claimReasons(),
        ];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->claimRepository->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->claimRepository->getFilters($request, $options),
        ] + $options;
    }
}
