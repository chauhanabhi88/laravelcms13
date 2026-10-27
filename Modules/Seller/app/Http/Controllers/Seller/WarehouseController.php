<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Controllers\Seller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Seller\Exceptions\SellerException;
use Modules\Seller\Http\Requests\Seller\WarehouseRequest;
use Modules\Seller\Models\SellerWarehouse;
use Modules\Seller\Repositories\SellerWarehouseRepository;
use Modules\Seller\Services\WarehouseService;

/**
 * Settings › Warehouses: pickup addresses, one default.
 */
class WarehouseController extends SellerPanelController
{
    public function __construct(
        private readonly SellerWarehouseRepository $warehouses,
        private readonly WarehouseService $service,
    ) {}

    public function index(): View
    {
        $seller = $this->seller();

        return view('seller::seller.settings.warehouses.index', [
            'seller' => $seller,
            'sellerStoreName' => $seller->displayName(),
            'warehouses' => $this->warehouses->forSeller($seller->id),
        ]);
    }

    public function create(): View
    {
        return view('seller::seller.settings.warehouses.form', [
            'sellerStoreName' => $this->seller()->displayName(),
            'warehouse' => null,
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        try {
            $this->service->save($this->seller(), $request->warehouseData(), null, $request->boolean('make_default'));
        } catch (SellerException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(frontRoute('seller.warehouse.index'))->with('success', trans('seller::panel.warehouses.messages.saved'));
    }

    public function edit(Request $request): View
    {
        $warehouse = $this->findOwned($request);

        return view('seller::seller.settings.warehouses.form', [
            'sellerStoreName' => $this->seller()->displayName(),
            'warehouse' => $warehouse,
        ]);
    }

    public function update(WarehouseRequest $request): RedirectResponse
    {
        $warehouse = $this->findOwned($request);

        try {
            $this->service->save($this->seller(), $request->warehouseData(), $warehouse, $request->boolean('make_default'));
        } catch (SellerException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(frontRoute('seller.warehouse.index'))->with('success', trans('seller::panel.warehouses.messages.saved'));
    }

    public function makeDefault(Request $request): RedirectResponse
    {
        $this->service->makeDefault($this->findOwned($request));

        return redirect()->to(frontRoute('seller.warehouse.index'))->with('success', trans('seller::panel.warehouses.messages.default_changed'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        try {
            $this->service->delete($this->findOwned($request));
        } catch (SellerException $e) {
            return redirect()->to(frontRoute('seller.warehouse.index'))->with('error', $e->getMessage());
        }

        return redirect()->to(frontRoute('seller.warehouse.index'))->with('success', trans('seller::panel.warehouses.messages.deleted'));
    }

    private function findOwned(Request $request): SellerWarehouse
    {
        $warehouse = SellerWarehouse::query()->findOrFail((int) $request->route('warehouse'));
        $this->authorizeOwned($warehouse);

        return $warehouse;
    }
}
