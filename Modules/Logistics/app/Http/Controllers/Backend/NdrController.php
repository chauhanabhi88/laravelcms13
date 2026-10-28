<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Logistics\Http\Requests\NdrActionRequest;
use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Repositories\NdrCaseRepository;
use Modules\Logistics\Services\NdrService;
use Modules\Logistics\Support\LogisticsOptions;

/**
 * NDR queue for Logistics Ops (FR-LOG-008): open failed deliveries with their attempts, and
 * the action (re-attempt, new address/phone, cancel → RTO) passed to the courier.
 */
class NdrController extends BackendController
{
    public function __construct(private NdrCaseRepository $cases)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('logistics.cache.ndr_case'), $request->input('per_page'))]);

            return view('logistics::backend.ndr_case.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('logistics.cache.ndr_case'), $request->input('per_page'))]);
            setFilterSession(config('logistics.cache.ndr_case'), $request);

            $content = view('logistics::backend.ndr_case.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.ndr.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function act(NdrActionRequest $request, NdrService $ndr): RedirectResponse
    {
        try {
            $case = $this->cases->find((int) $request->route('id'));

            if (! $case instanceof NdrCase) {
                throw new \RuntimeException(trans('logistics::ndr_case.messages.not_found'));
            }

            $ndr->act($case, (string) $request->validated('action'), $request->actionPayload(), 'admin:'.$request->user()?->getKey());

            return redirect()->back()->with('success', trans('logistics::ndr_case.messages.actioned'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $options = ['statuses' => LogisticsOptions::ndrStatuses(), 'actions' => LogisticsOptions::ndrActions()];

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->cases->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->cases->getFilters($request, $options),
        ] + $options;
    }
}
