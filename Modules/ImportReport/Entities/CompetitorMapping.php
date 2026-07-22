<?php

namespace Modules\ImportReport\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompetitorMapping extends Model
{
    
    
    protected $table = 'competitor_mapping';
    

    protected $fillable = ["sku","ref_sku","ref_url","ref_name","ref_product_exists","ignor","send_in_feed","priority","piece_multiplier","piece_count","shipping_method","name","brand_value","mpn"];

}
