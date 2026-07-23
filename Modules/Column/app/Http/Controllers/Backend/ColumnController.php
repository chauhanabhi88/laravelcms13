<?php

namespace Modules\Column\Http\Controllers\Backend;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Column\Http\Requests\CreateRequest;
use Modules\Column\Http\Requests\UpdateRequest;
use Modules\Column\Models\Column;
use Modules\Column\Models\ColumnsMapping;
use Modules\Column\Repositories\ColumnRepository;
use Modules\Core\Http\Controllers\BackendController;
use Modules\Menu\Models\Menu;

class ColumnController extends BackendController
{
    /**
     * @var ColumnRepository
     */
    private $column;

    private $columnEntity;

    public function __construct(ColumnRepository $columnRepo, Column $column)
    {
        parent::__construct();

        $this->column = $columnRepo;
        $this->columnEntity = $column;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config('column.cache.name'), $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            // $columns = $this->column->sortColumns();
            $activeMenuId = getActiveMenuId($request);
            $columns = getColumnObject()->getColumns($activeMenuId);
            $collection = $this->column->pagination($request);
            $yesNoOptions = $this->column->getYesNoOptions(true);
            $menuOptions = $this->column->getMenuOptions();
            $filters = $this->column->getFilters($request, $yesNoOptions, $menuOptions);
            $statusOptions = $this->column->getStatusOptions(true);

            return view('column::backend.index', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'yesNoOptions', 'activeMenuId'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.dashboard.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function filters(Request $request)
    {
        try {
            if (function_exists('getPerPageForModule')) {
                $perPage = getPerPageForModule(config('column.cache.name'), $request->get('per_page'));
                $request->merge(['per_page' => $perPage]);
            }
            setFilterSession(config('column.cache.name'), $request);
            // $columns = $this->column->sortColumns();
            $yesNoOptions = $this->column->getYesNoOptions(true);
            $menuOptions = $this->column->getMenuOptions();
            $filters = $this->column->getFilters($request, $yesNoOptions, $menuOptions);
            $collection = $this->column->pagination($request);
            $statusOptions = $this->column->getStatusOptions(true);
            $activeMenuId = getActiveMenuId($request, 'admin.column.index');
            $columns = getColumnObject()->getColumns($activeMenuId);

            $content = view('column::backend.partials.grid', compact('request', 'collection', 'columns', 'filters', 'statusOptions', 'yesNoOptions', 'activeMenuId'));

            return response()->json([
                'type' => 'success',
                'content' => [
                    'element' => 'collection',
                    'html' => $content->__toString(),
                ],
                'message' => $request->get('message'),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        try {
            $yesNoOptions = $this->column->getYesNoOptions(true);
            $menuOptions = $this->column->getMenuOptions();

            return view('column::backend.create', compact('yesNoOptions', 'menuOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.column.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(CreateRequest $request)
    {
        try {
            $params = $request->all();
            $rules = [
                'code' => [
                    'required',
                    Rule::unique('columns')->where(function ($query) use ($params) {
                        return $query->where('menu_id', $params['column']['menu_id'] ?? null);
                    }),
                ],
                'menu_id' => 'required|exists:menu,id',
                'name' => 'required|min:2',
            ];
            $validator = Validator::make($params['column'], $rules, [
                'required' => trans('column::column.messages.required_field'),
                'min' => trans('column::column.messages.min_message'),
                'code.unique' => trans('column::column.messages.code_exists'),
                'menu_id.exists' => trans('column::column.messages.menu_not_exists'),
            ]);
            if ($validator->fails()) {
                return redirect()->route('admin.column.create', updateUrlParams())->with('error', $validator->errors()->first());
            }

            $column = $this->column->create($params['column']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.column.edit', updateUrlParams([$column->id]))->with('success', trans('column::column.messages.created_success'));
            }

            return redirect()->route('admin.column.index', updateUrlParams())->with('success', trans('column::column.messages.created_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.column.create', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new Exception(trans('column::column.messages.data_invalid'));
            }
            $column = $this->column->find($id);
            if (! $column) {
                throw new Exception(trans('column::column.messages.data_invalid'));
            }
            $yesNoOptions = $this->column->getYesNoOptions(true);
            $menuOptions = $this->column->getMenuOptions();

            return view('column::backend.edit', compact('column', 'yesNoOptions', 'menuOptions'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.column.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(UpdateRequest $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new Exception(trans('column::column.messages.data_invalid'));
            }
            $params = $request->all();
            $column = $this->column->find($id);
            if (! $column) {
                throw new Exception(trans('column::column.messages.data_invalid'));
            }
            $rules = [
                'code' => [
                    'required',
                    Rule::unique('columns')->ignore($id)->where(function ($query) use ($params) {
                        return $query->where('menu_id', $params['column']['menu_id'] ?? null);
                    }),
                ],
                'menu_id' => 'required|exists:menu,id',
                'name' => 'required|min:2',
            ];
            $validator = Validator::make($params['column'], $rules, [
                'required' => trans('column::column.messages.required_field'),
                'min' => trans('column::column.messages.min_message'),
                'code.unique' => trans('column::column.messages.code_exists'),
                'menu_id.exists' => trans('column::column.messages.menu_not_exists'),
            ]);
            if ($validator->fails()) {
                return redirect()->route('admin.column.edit', updateUrlParams(['id' => $id]))->with('error', $validator->errors()->first());
            }
            $this->column->update($column, $params['column']);
            if (isset($params['snc']) && $params['snc']) {
                return redirect()->route('admin.column.edit', updateUrlParams([$id]))->with('success', trans('column::column.messages.updated_success'));
            }

            return redirect()->route('admin.column.index', updateUrlParams())->with('success', trans('column::column.messages.updated_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.column.edit', updateUrlParams([$id]))->with('error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function delete(Request $request)
    {
        try {
            $id = $request->id;
            if (! $id) {
                throw new Exception(trans('column::column.messages.data_invalid'));
            }
            $column = $this->column->find($id);
            if (! $column) {
                throw new Exception(trans('column::column.messages.data_invalid'));
            }

            $this->column->destroy($column);

            return redirect()->route('admin.column.index', updateUrlParams())->with('success', trans('column::column.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.column.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    /**
     * Remove Selected / All resource from storage
     */
    public function massDelete(Request $request)
    {
        try {
            $this->column->destroyMultiple($request);

            return redirect()->route('admin.column.index', updateUrlParams())->with('success', trans('column::column.messages.deleted_success'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.column.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function updateStatus(Request $request)
    {
        try {
            if ($request->get('id')) {
                $id = $request->get('id');
                $status = $request->get('status');
                $columnRow = $this->column->find($id);
                if (! $columnRow) {
                    throw new Exception(trans('column::column.messages.data_invalid'));
                }
                $status = ($status == 1) ? config('core.enabled') : config('core.disabled');
                $params = ['status' => $status];
                $this->column->update($columnRow, $params);
            }
            $gridRequest = new Request;
            $gridRequest->merge([
                'active_menu_id' => $request->get('active_menu_id'),
                'message' => trans('core::core.messages.status_change_success'),
            ]);

            return $this->filters($gridRequest);
        } catch (\Throwable $e) {
            return redirect()->route('admin.column.index', updateUrlParams())->with('error', $e->getMessage());
        }
    }

    public function saveDefaultColumns(Request $request)
    {
        try {
            $data = $request->all();
            if (! isset($data['columns'])) {
                throw new Exception(trans('core::core.messages.invalid_column_data'), 1);
            }
            $user = auth()->user();
            $menuId = $request->get('active_menu_id');
            $columns = $request->get('columns');
            $data = [];
            foreach ($columns as $columnId => $checked) {
                $data[] = [
                    'column_id' => $columnId,
                    'admin_id' => $user->id,
                    'checkbox_checked' => ($checked == 'true') ? 1 : 0,
                ];
            }
            ColumnsMapping::upsert($data, ['column_id', 'admin_id'], ['checkbox_checked']);

            $menu = Menu::find($menuId);

            return response()->json(['type' => 'success', 'message' => trans('core::core.messages.default_column_saved'), 'redirectUrl' => route($menu->link, updateUrlParams())]);
        } catch (\Throwable $th) {
            return response()->json(['type' => 'error', 'message' => $th->getMessage()]);
        }
    }
}
