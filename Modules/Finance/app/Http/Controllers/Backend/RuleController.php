<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Controllers\Backend;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Finance\Http\Controllers\Backend\Concerns\RendersFinanceGrid;
use Modules\Finance\Http\Requests\CommissionRuleRequest;
use Modules\Finance\Services\FinanceRuleService;
use Modules\Finance\Support\FinanceOptions;
use Modules\Seller\Models\Seller;

/**
 * Shared CRUD of commission and fee rules (plan M17 §3.1/§3.2): grid, create, edit, delete.
 */
abstract class RuleController extends BackendController
{
    use RendersFinanceGrid;

    public function __construct(protected FinanceRuleService $rules)
    {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        return $this->renderIndex($request);
    }

    public function create(): View
    {
        return view('finance::backend.'.static::VIEW.'.form', ['rule' => null] + $this->formOptions());
    }

    public function store(CommissionRuleRequest $request): RedirectResponse
    {
        try {
            $rule = $this->save(null, $request->validated(), $request);

            return $this->afterSave($request, $rule, 'created');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $rule = $this->gridRepository()->find((int) $request->route('id'));

        if ($rule === null) {
            return redirect()->route('admin.'.static::VIEW.'.index', updateUrlParams())->with('error', trans('finance::finance.messages.not_found'));
        }

        return view('finance::backend.'.static::VIEW.'.form', ['rule' => $rule] + $this->formOptions());
    }

    public function update(CommissionRuleRequest $request): RedirectResponse
    {
        try {
            $rule = $this->gridRepository()->find((int) $request->route('id'));

            if ($rule === null) {
                throw new \RuntimeException(trans('finance::finance.messages.not_found'));
            }

            $this->save($rule, $request->validated(), $request);

            return $this->afterSave($request, $rule, 'updated');
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function delete(Request $request): RedirectResponse
    {
        try {
            $rule = $this->gridRepository()->find((int) $request->route('id'));

            if ($rule !== null) {
                $this->rules->delete($rule);
            }

            return redirect()->route('admin.'.static::VIEW.'.index', updateUrlParams())->with('success', trans('finance::finance.messages.rule_deleted'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    abstract protected function save(?Model $rule, array $data, Request $request): Model;

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'scopes' => FinanceOptions::scopes(),
            'categoryOptions' => app(CategoryTreeService::class)->options(),
            'sellerOptions' => Seller::query()->orderBy('store_name')->limit(1000)->get(['id', 'code', 'store_name', 'legal_name'])
                ->mapWithKeys(fn (Seller $seller): array => [$seller->id => $seller->code.' · '.$seller->displayName()])
                ->all(),
            'statusOptions' => FinanceOptions::enabledOptions(),
        ];
    }

    protected function afterSave(Request $request, Model $rule, string $action): RedirectResponse
    {
        $route = $request->input('snc') ? 'admin.'.static::VIEW.'.edit' : 'admin.'.static::VIEW.'.index';
        $params = $request->input('snc') ? updateUrlParams([$rule->getKey()]) : updateUrlParams();

        return redirect()->route($route, $params)->with('success', trans('finance::finance.messages.rule_'.$action));
    }
}
