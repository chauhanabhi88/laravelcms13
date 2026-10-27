<?php

namespace Modules\Directory\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Directory\Http\Requests\DirectoryPincodeUpdateRequest;
use Modules\Directory\Models\DirectoryPincode;
use Modules\Directory\Repositories\DirectoryPincodeRepository;

/**
 * Pincode master grid. Rows come from directory:import-pincodes; admins maintain the
 * serviceability / COD / zone flags, which apply to every office of the pincode.
 */
class DirectoryPincodeController extends BackendController
{
    public function __construct(private DirectoryPincodeRepository $pincode)
    {
        parent::__construct();
    }

    public function index(Request $request)
    {
        try {
            $perPage = getPerPageForModule(config('directory.cache.pincode'), $request->get('per_page'));
            $request->merge(['per_page' => $perPage]);

            return view('directory::backend.directory_pincode.index', $this->gridData($request, getActiveMenuId($request)));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function filters(Request $request)
    {
        try {
            $perPage = getPerPageForModule(config('directory.cache.pincode'), $request->get('per_page'));
            $request->merge(['per_page' => $perPage]);
            setFilterSession(config('directory.cache.pincode'), $request);

            $content = view('directory::backend.directory_pincode.partials.grid', $this->gridData($request, getActiveMenuId($request, 'admin.directory_pincode.index')));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString(),
                ],
                'message' => $request->get('message'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function edit(Request $request)
    {
        try {
            $directoryPincode = $this->findOrFail($request->id);
            $stateOptions = $this->pincode->getStateOptions(true);
            $yesNoOptions = $this->pincode->getYesNoOptions();
            $zoneOptions = $this->pincode->getZoneOptions(true);

            return view('directory::backend.directory_pincode.edit', compact('directoryPincode', 'stateOptions', 'yesNoOptions', 'zoneOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.directory_pincode.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function update(DirectoryPincodeUpdateRequest $request)
    {
        $id = $request->id;

        try {
            $directoryPincode = $this->findOrFail($id);
            $data = $request->validated()['directory_pincode'];

            DB::transaction(function () use ($directoryPincode, $data) {
                $this->pincode->update($directoryPincode, Arr::except($data, DirectoryPincode::PINCODE_LEVEL_FIELDS));
                $this->pincode->updateFlags($directoryPincode->pincode, Arr::only($data, DirectoryPincode::PINCODE_LEVEL_FIELDS) + ['zone' => null]);
            });

            if ($request->get('snc')) {
                return redirect()->route('admin.directory_pincode.edit', updateUrlParams([$id]))->with('success', trans('directory::directory_pincode.messages.updated_success'));
            }

            return redirect()->route('admin.directory_pincode.index', updateUrlParams())->with('success', trans('directory::directory_pincode.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.directory_pincode.edit', updateUrlParams([$id]))->with('error', $e->getMessage());
        }
    }

    /**
     * Grid toggle for "is_serviceable" or "cod_allowed"; applies to the whole pincode.
     */
    public function updateStatus(Request $request)
    {
        $field = $request->get('field');

        if ($request->get('id') && in_array($field, ['is_serviceable', 'cod_allowed'], true)) {
            $directoryPincode = $this->pincode->find($request->get('id'));

            if ($directoryPincode) {
                $value = $request->get('status') == 1 ? config('core.yes') : config('core.no');
                $this->pincode->updateFlags($directoryPincode->pincode, [$field => $value]);
            }
        }

        $refresh = new Request(['active_menu_id' => $request->get('active_menu_id')]);
        $refresh->merge(['message' => trans('core::core.messages.status_change_success')]);

        return $this->filters($refresh);
    }

    /**
     * @return array<string, mixed>
     */
    private function gridData(Request $request, $menuId): array
    {
        return [
            'request' => $request,
            'activeMenuId' => $menuId,
            'collection' => $this->pincode->pagination($request),
            'columns' => getColumnObject()->getColumns($menuId),
            'filters' => $this->pincode->getFilters($request),
            'yesNoOptions' => $this->pincode->getYesNoOptions(),
            'zoneOptions' => $this->pincode->getZoneOptions(),
        ];
    }

    private function findOrFail($id): DirectoryPincode
    {
        $directoryPincode = $id ? $this->pincode->find($id) : null;

        if (! $directoryPincode) {
            throw new \Exception(trans('directory::directory_pincode.messages.data_invalid'));
        }

        return $directoryPincode;
    }
}
