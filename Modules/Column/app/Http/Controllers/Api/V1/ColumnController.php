<?php

namespace Modules\Column\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Column\Models\Column;
use Illuminate\Support\Facades\Auth;
use Modules\Column\Models\ColumnsMapping;
use Modules\Column\Repositories\ColumnRepository;
use Modules\Menu\Models\Menu;
class ColumnController extends Controller
{
    protected $columnRepository;

    // Dependency injection: Laravel resolves the interface from your ServiceProvider
    public function __construct(ColumnRepository $columnRepository)
    {
        $this->columnRepository = $columnRepository;
    }

    function saveDefaultColumns(Request $request)
    {
        try {
            $data = $request->all();
            if (!isset($data['columns'])) {
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
                    'checkbox_checked' => ($checked == 'true' || $checked == 1) ? 1 : 0
                ];
            }
            ColumnsMapping::upsert($data, ['column_id', 'admin_id'], ['checkbox_checked']);
            
            $menu = Menu::find($menuId);
            return response()->json(['type' => 'success', 'message' => trans('core::core.messages.default_column_saved'), "redirectUrl" => route($menu->link, updateUrlParams())]);
        } catch (\Throwable $th) {
            return response()->json(['type' => 'error', 'message' => $th->getMessage()]);
        }
    }
}
