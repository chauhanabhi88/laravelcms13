<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Exceptions\InvalidStateTransition;
use Modules\Seller\Exceptions\SellerException;
use Modules\Seller\Http\Requests\Backend\SellerDecisionRequest;
use Modules\Seller\Models\Seller;
use Modules\Seller\Repositories\SellerRepository;
use Modules\Seller\Services\SellerVerificationService;
use Modules\User\Models\User;

/**
 * KYC queue: sellers waiting for verification (or for their requested changes), the
 * application detail and the approve / reject / request-changes decisions.
 */
class SellerApplicationController extends SellerAdminController
{
    public function __construct(
        SellerRepository $sellers,
        private readonly SellerVerificationService $verification,
    ) {
        parent::__construct($sellers);
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('seller.cache.seller_application'), $request->input('per_page'))]);

            return view('seller::backend.seller_application.index', $this->gridData($request, SellerRepository::GRID_APPLICATIONS, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('seller.cache.seller_application'), $request->input('per_page'))]);
            setFilterSession(config('seller.cache.seller_application'), $request);

            $content = view('seller::backend.seller_application.partials.grid', $this->gridData(
                $request,
                SellerRepository::GRID_APPLICATIONS,
                getActiveMenuId($request, 'admin.seller_application.index'),
            ));

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
            return view('seller::backend.seller.show', $this->detailData($this->findOrFail($request->route('id')), 'application'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.seller_application.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function approve(SellerDecisionRequest $request): RedirectResponse
    {
        return $this->decide($request, fn ($seller, $admin, $reason) => $this->verification->approve($seller, $admin, $reason), 'approved');
    }

    public function reject(SellerDecisionRequest $request): RedirectResponse
    {
        return $this->decide($request, fn ($seller, $admin, $reason) => $this->verification->reject($seller, $admin, $reason), 'rejected');
    }

    public function requestChanges(SellerDecisionRequest $request): RedirectResponse
    {
        return $this->decide($request, fn ($seller, $admin, $reason) => $this->verification->requestChanges($seller, $admin, $reason), 'changes_requested');
    }

    /**
     * @param  \Closure(Seller, User, ?string): mixed  $action
     */
    private function decide(SellerDecisionRequest $request, \Closure $action, string $message): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $action($this->findOrFail($id), $this->admin(), $request->reason());
        } catch (SellerException|InvalidStateTransition $e) {
            return redirect()->route('admin.seller_application.show', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.seller_application.show', updateUrlParams([$id]))->with('error', $e->getMessage());
        }

        return redirect()->route('admin.seller_application.index', updateUrlParams())
            ->with('success', trans('seller::seller.messages.'.$message));
    }
}
