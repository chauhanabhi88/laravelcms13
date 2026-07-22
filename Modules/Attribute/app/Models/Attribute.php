<?php

namespace Modules\Attribute\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Repositories\Transaltion\Translatable;
use Modules\Attribute\Models\AttributeTranslation;

class Attribute extends Model
{
    use Translatable;

	protected $table = 'attribute';

    public $translatedAttributes = ['name'];

    protected $fillable = [
    	'code',
    	'input_type',
    	'custom_value',
    	'is_required'
	];
	
	//Please Don't remove below Line
	//AppendFunctionHere
    /**
    * relationship with Modules\Attribute\Models\AttributeOption
    *
    */
    /*public function attributeOption()
    {
        return $this->hasMany('Modules\Attribute\Models\AttributeOption', 'attribute_id')->select('custom_option')->orderBy('sort_order','asc');
    }*/
    public function attributeOption()
    {
        return $this->hasMany('Modules\Attribute\Models\AttributeOption', 'attribute_id');
    }
}