<?php

namespace Modules\Attribute\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeOptionTranslation extends Model
{
	protected $table = 'attribute_option_translation';
    protected $fillable = ['name'];

    //Please Don't remove below Line
    //AppendFunctionHere
     public function attributeOption() {
        return $this->belongsTo('Modules\Attribute\Models\AttributeOption', 'attribute_option_id');
    }
}
