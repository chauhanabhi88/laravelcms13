<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    protected $table = 'entity_relations';
    
    protected $fillable = [
        'join_type',
        'base_module',
    	'base_entity',
        'target_module',
        'target_entity',
        'target_foreign_key'
    ];

    //Please Don't remove below Line
	//AppendFunctionHere
}