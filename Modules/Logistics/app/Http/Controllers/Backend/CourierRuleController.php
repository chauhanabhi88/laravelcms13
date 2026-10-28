<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Logistics\DTO\AssignmentContext;
use Modules\Logistics\Http\Requests\CourierRuleRequest;
use Modules\Logistics\Http\Requests\SimulateCourierRuleRequest;
use Modules\Logistics\Models\CourierRule;
use Modules\Logistics\Repositories\CourierRepository;
use Modules\Logistics\Repositories\CourierRuleRepository;
use Modules\Logistics\Services\CourierAssignmentService;
use Modules\Logistics\Support\LogisticsOptions;

/**
 * Courier rules (FR-LOG-003): CRUD with a rule builder (lower priority is evaluated first),
 * and a simulator: pincode, weight and payment → the rule that matches and the courier and
 * carrier that would be chosen.
 */
class CourierRuleController extends BackendController
{
    public function __construct(
        private CourierRuleRepository $rules,
        private CourierRepository $couriers,
        private CourierAssignmentService $assignment,
    ) {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('logistics.cache.courier_rule'), $request->input('per_page'))]);

            return view('logistics::backend.courier_rule.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('logistics.cache.courier_rule'), $request->input('per_page'))]);
            setFilterSession(config('logistics.cache.courier_rule'), $request);

            $content = view('logistics::backend.courier_rule.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.courier_rule.index')));

            return response()->json([
                'type' => 'success',
                'content' => ['element' => 'collection', 'html' => $content->render()],
                'message' => $request->input('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['type' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function create(): View|RedirectResponse
    {
        try {
            return view('logistics::backend.courier_rule.create', $this->formData());
        } catch (\Throwable $e) {
            return redirect()->route('admin.courier_rule.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function store(CourierRuleRequest $request): RedirectResponse
    {
        try {
            $rule = $this->rules->create($request->payload());

            if ($request->input('snc')) {
                return redirect()->route('admin.courier_rule.edit', updateUrlParams([$rule->id]))->with('success', trans('logistics::courier_rule.messages.created_success'));
            }

            return redirect()->route('admin.courier_rule.index', updateUrlParams())->with('success', trans('logistics::courier_rule.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.courier_rule.create', updateUrlParams())->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        try {
            return view('logistics::backend.courier_rule.edit', $this->formData() + ['rule' => $this->findOrFail($request->route('id'))]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.courier_rule.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(CourierRuleRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $this->rules->update($this->findOrFail($id), $request->payload());

            if ($request->input('snc')) {
                return redirect()->route('admin.courier_rule.edit', updateUrlParams([$id]))->with('success', trans('logistics::courier_rule.messages.updated_success'));
            }

            return redirect()->route('admin.courier_rule.index', updateUrlParams())->with('success', trans('logistics::courier_rule.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.courier_rule.edit', updateUrlParams([$id]))->withInput()->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if ($request->input('id') && ($rule = $this->rules->find($request->input('id')))) {
            $status = (int) $request->input('status') === (int) config('core.enabled') ? config('core.enabled') : config('core.disabled');
            $this->rules->update($rule, ['status' => $status]);
        }

        $refresh = new Request(['active_menu_id' => $request->input('active_menu_id')]);
        $refresh->merge(['message' => trans('core::core.messages.status_change_success')]);

        return $this->filters($refresh);
    }

    public function delete(Request $request): RedirectResponse
    {
        try {
            $this->rules->destroy($this->findOrFail($request->route('id')));

            return redirect()->route('admin.courier_rule.index', updateUrlParams())->with('success', trans('logistics::courier_rule.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.courier_rule.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * GET: the simulator form; POST: the rule, courier and carrier chosen for the input.
     */
    public function simulate(Request $request): View|RedirectResponse
    {
        $result = null;

        if ($request->isMethod('post')) {
            $input = app(SimulateCourierRuleRequest::class)->validated();
            $pincode = DirectoryPincode::query()->where('pincode', $input['destination_pincode'])->orderBy('id')->first();

            $context = new AssignmentContext(
                $input['origin_pincode'],
                $input['destination_pincode'],
                $pincode?->zone,
                $pincode?->state_id,
                (int) $input['weight_grams'],
                $input['payment'],
                isset($input['seller_id']) ? (int) $input['seller_id'] : null,
                isset($input['category_id']) ? (int) $input['category_id'] : null,
            );

            try {
                $result = [
                    'context' => $context,
                    'rule' => $this->assignment->matchRule($context),
                    'assignment' => $this->assignment->resolve($context),
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                $result = ['context' => $context, 'rule' => null, 'assignment' => null, 'error' => $e->getMessage()];
            }
        }

        return view('logistics::backend.courier_rule.simulate', [
            'result' => $result,
            'strategies' => LogisticsOptions::strategies(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $statusOptions = $this->rules->getStatusOptions();

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->rules->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->rules->getFilters($request, [
                'couriers' => $this->couriers->getOptions(false, true),
                'strategies' => LogisticsOptions::strategies(),
                'statuses' => $statusOptions,
            ]),
            'statusOptions' => $statusOptions,
            'strategies' => LogisticsOptions::strategies(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'couriers' => $this->couriers->getOptions(true, true),
            'strategies' => LogisticsOptions::strategies(),
            'zones' => LogisticsOptions::zones(),
            'paymentModes' => LogisticsOptions::paymentModes(),
        ];
    }

    private function findOrFail(mixed $id): CourierRule
    {
        $rule = $id ? $this->rules->find((int) $id) : null;

        if (! $rule instanceof CourierRule) {
            throw new \RuntimeException(trans('logistics::courier_rule.messages.data_invalid'));
        }

        return $rule;
    }
}
