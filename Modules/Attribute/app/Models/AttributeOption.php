<?php

namespace Modules\Attribute\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Repositories\Transaltion\Translatable;

class AttributeOption extends Model
{
    use Translatable;

    protected $table = 'attribute_option';

    public $translatedAttributes = ['name'];

    protected $fillable = [
        'attribute_id',
        'custom_option',
        'default',
        'child_attribute_id',
        'sort_order',
        'image',
    ];

    // Please Don't remove below Line
    // AppendFunctionHere
    /**
     * relationship with Modules\Attribute\Models\Attribute
     */
    public function attribute()
    {
        return $this->belongsTo('Modules\Attribute\Models\Attribute', 'attribute_id');
    }

    public function attributeTranslation()
    {
        return $this->hasOne('Modules\Attribute\Models\AttributeOptionTranslation', 'attribute_option_id')->where('locale', '=', array_key_exists('locale', updateUrlParams()) ? updateUrlParams()['locale'] : 'en');
    }
}
