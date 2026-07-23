<?php

namespace Modules\Column\Models;

use Illuminate\Database\Eloquent\Model;

class Column extends Model
{
    protected $table = 'columns';

    protected $fillable = ['name', 'code', 'description', 'sort_order', 'is_default', 'menu_id', 'is_sortable'];

    /**
     * Get columns for a given menu id
     *
     * This function returns columns for a given menu id. It also checks if the user has a custom mapping for the columns.
     * If the user has a custom mapping, it will return the columns with the checkbox checked value from the mapping.
     * If the user does not have a custom mapping or all columns are unchecked, it will return the columns with the checkbox checked value set to the is_default value of the column.
     *
     * @param  int  $menuId
     * @return array
     */
    public function getColumns($menuId)
    {
        $user = auth()->user();
        $query = $this->query();
        $query->orderBy('sort_order', 'asc')
            ->where('menu_id', $menuId);

        $query->leftJoin('columns_mapping', function ($join) use ($user) {
            $join->on('columns_mapping.column_id', '=', 'columns.id')
                ->where('columns_mapping.admin_id', $user->id);

        })
            ->selectRaw('columns.*, columns_mapping.checkbox_checked');
        $columns = $query->get()->toArray();
        $mappings = array_unique(array_column($columns, 'checkbox_checked'));
        if (count($mappings) > 1 || in_array(1, $mappings)) {
            return $columns;
        }
        foreach ($columns as $key => $column) {
            $columns[$key]['checkbox_checked'] = $column['is_default'] == 1 ? 1 : 0;
        }

        return $columns;
    }

    public function userColumnMapping()
    {
        return $this->hasOne('Modules\Column\Models\ColumnsMapping', 'column_id', 'id');
    }
}
