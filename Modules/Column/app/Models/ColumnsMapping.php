<?php

namespace Modules\Column\Models;

use Illuminate\Database\Eloquent\Model;

class ColumnsMapping extends Model
{
    protected $table = 'columns_mapping';

    protected $fillable = ['column_id', 'admin_id', 'checkbox_checked'];
}
