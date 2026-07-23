<?php

namespace Modules\Attribute\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Repositories\Transaltion\Translatable;

class Attribute extends Model
{
    use Translatable;

    protected $table = 'attribute';

    public $translatedAttributes = ['name'];

    protected $fillable = [
        'code',
        'input_type',
        'custom_value',
        'is_required',
    ];

    // Please Don't remove below Line
    // AppendFunctionHere
    /**
     * relationship with Modules\Attribute\Models\AttributeOption
     */
    public function attributeOption()
    {
        return $this->hasMany('Modules\Attribute\Models\AttributeOption', 'attribute_id');
    }
}
