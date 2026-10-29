<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Controllers\Backend;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Promotion\Http\Controllers\Backend\Concerns\RendersPromotionGrid;
use Modules\Promotion\Http\Requests\FlashDealRequest;
use Modules\Promotion\Models\FlashDeal;
use Modules\Promotion\Services\FlashDealService;

/**
 * Flash deals (FR-PRM-003, plan M18 §6): grid, create, edit with its variant rows (deal price,
 * unit cap, units sold), delete. Variants are added by system SKU.
 */
class FlashDealController extends BackendController
{
    use RendersPromotionGrid;

    protected const GRID = 'flash_deal';

    public function __construct(private readonly FlashDealService $deals)
    {
        parent::__construct();
    }

    public function create(): View
    {
        return view('promotion::backend.flash_deal.form', ['deal' => null, 'items' => collect()]);
    }

    public function store(FlashDealRequest $request): RedirectResponse
    {
        return $this->save($request, null, 'created');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $deal = FlashDeal::query()->find((int) $request->route('id'));

        if ($deal === null) {
            return redirect()->route('admin.flash_deal.index', updateUrlParams())->with('error', trans('promotion::flash_deal.messages.not_found'));
        }

        $items = $deal->items()->with(['variant', 'product.translations'])->orderBy('id')->get();

        return view('promotion::backend.flash_deal.form', ['deal' => $deal, 'items' => $items]);
    }

    public function update(FlashDealRequest $request): RedirectResponse
    {
        $deal = FlashDeal::query()->find((int) $request->route('id'));

        if ($deal === null) {
            return redirect()->route('admin.flash_deal.index', updateUrlParams())->with('error', trans('promotion::flash_deal.messages.not_found'));
        }

        return $this->save($request, $deal, 'updated');
    }

    public function delete(Request $request): RedirectResponse
    {
        try {
            $deal = FlashDeal::query()->find((int) $request->route('id'));

            if ($deal !== null) {
                $this->deals->delete($deal);
            }

            return redirect()->route('admin.flash_deal.index', updateUrlParams())->with('success', trans('promotion::flash_deal.messages.deleted'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    protected function save(FlashDealRequest $request, ?FlashDeal $deal, string $action): RedirectResponse
    {
        try {
            $deal = $this->deals->save($deal, $request->dealData(), (array) $request->validated('items', []), array_values((array) $request->validated('new_items', [])));
        } catch (ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors())->with('error', trans('promotion::flash_deal.messages.rows_invalid'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.flash_deal.edit', updateUrlParams([$deal->id]))->with('success', trans('promotion::flash_deal.messages.'.$action));
    }
}
