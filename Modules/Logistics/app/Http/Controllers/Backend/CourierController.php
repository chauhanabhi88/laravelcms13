<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Backend;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Logistics\Http\Requests\CourierRequest;
use Modules\Logistics\Models\Courier;
use Modules\Logistics\Models\CourierRule;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Repositories\CourierRepository;
use Modules\Logistics\Support\CourierDriverManager;
use Modules\Logistics\Support\LogisticsOptions;

/**
 * Courier master (FR-LOG-002): name, driver, credentials, COD / reverse support, priority,
 * status, and a connection test against the provider.
 */
class CourierController extends BackendController
{
    /**
     * Credential keys kept as typed (escapeHtml would alter passwords).
     */
    private const RAW_FIELDS = ['email', 'password'];

    public function __construct(
        private CourierRepository $couriers,
        private CourierDriverManager $drivers,
    ) {
        parent::__construct();
    }

    public function index(Request $request): View|RedirectResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('logistics.cache.courier'), $request->input('per_page'))]);

            return view('logistics::backend.courier.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request): JsonResponse
    {
        try {
            $request->merge(['per_page' => getPerPageForModule(config('logistics.cache.courier'), $request->input('per_page'))]);
            setFilterSession(config('logistics.cache.courier'), $request);

            $content = view('logistics::backend.courier.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.courier.index')));

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
            return view('logistics::backend.courier.create', $this->formData());
        } catch (\Throwable $e) {
            return redirect()->route('admin.courier.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function store(CourierRequest $request): RedirectResponse
    {
        try {
            $courier = $this->couriers->create($request->payload(), self::RAW_FIELDS);

            if ($request->input('snc')) {
                return redirect()->route('admin.courier.edit', updateUrlParams([$courier->id]))->with('success', trans('logistics::courier.messages.created_success'));
            }

            return redirect()->route('admin.courier.index', updateUrlParams())->with('success', trans('logistics::courier.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.courier.create', updateUrlParams())->withInput($request->except('credentials_password'))->with('error', $e->getMessage());
        }
    }

    public function edit(Request $request): View|RedirectResponse
    {
        try {
            return view('logistics::backend.courier.edit', $this->formData() + ['courier' => $this->findOrFail($request->route('id'))]);
        } catch (\Throwable $e) {
            return redirect()->route('admin.courier.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(CourierRequest $request): RedirectResponse
    {
        $id = (int) $request->route('id');

        try {
            $courier = $this->findOrFail($id);
            $this->couriers->update($courier, $request->payload($courier), self::RAW_FIELDS);

            if ($request->input('snc')) {
                return redirect()->route('admin.courier.edit', updateUrlParams([$id]))->with('success', trans('logistics::courier.messages.updated_success'));
            }

            return redirect()->route('admin.courier.index', updateUrlParams())->with('success', trans('logistics::courier.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.courier.edit', updateUrlParams([$id]))->withInput($request->except('credentials_password'))->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if ($request->input('id') && ($courier = $this->couriers->find($request->input('id')))) {
            $status = (int) $request->input('status') === (int) config('core.enabled') ? config('core.enabled') : config('core.disabled');
            $this->couriers->update($courier, ['status' => $status]);
        }

        $refresh = new Request(['active_menu_id' => $request->input('active_menu_id')]);
        $refresh->merge(['message' => trans('core::core.messages.status_change_success')]);

        return $this->filters($refresh);
    }

    /**
     * A courier used by rules or shipments cannot be deleted (deactivate it instead).
     */
    public function delete(Request $request): RedirectResponse
    {
        try {
            $courier = $this->findOrFail($request->route('id') ?? $request->input('id'));

            if (CourierRule::query()->where('courier_id', $courier->id)->exists() || Shipment::query()->where('courier_id', $courier->id)->exists()) {
                throw new \RuntimeException(trans('logistics::courier.messages.in_use'));
            }

            $this->couriers->destroy($courier);

            return redirect()->route('admin.courier.index', updateUrlParams())->with('success', trans('logistics::courier.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.courier.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Log in with the courier's credentials (a fresh token for Shiprocket).
     */
    public function testConnection(Request $request): RedirectResponse
    {
        try {
            $courier = $this->findOrFail($request->route('id'));

            if ($courier->driver === Courier::DRIVER_SHIPROCKET) {
                $client = $this->drivers->shiprocketClient($courier);
                $client->forgetToken();
                $client->token();
            }

            return redirect()->back()->with('success', trans('logistics::courier.messages.connection_ok', ['name' => $courier->name]));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', trans('logistics::courier.messages.connection_failed', ['error' => $e->getMessage()]));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, mixed $menuId): array
    {
        $statusOptions = $this->couriers->getStatusOptions();

        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->couriers->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->couriers->getFilters($request, ['drivers' => LogisticsOptions::drivers(), 'statuses' => $statusOptions]),
            'statusOptions' => $statusOptions,
            'drivers' => LogisticsOptions::drivers(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'drivers' => LogisticsOptions::drivers(),
        ];
    }

    private function findOrFail(mixed $id): Courier
    {
        $courier = $id ? $this->couriers->find((int) $id) : null;

        if (! $courier instanceof Courier) {
            throw new \RuntimeException(trans('logistics::courier.messages.data_invalid'));
        }

        return $courier;
    }
}
