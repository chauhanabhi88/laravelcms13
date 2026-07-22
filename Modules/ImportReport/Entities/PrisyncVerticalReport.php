<?php

namespace Modules\ImportReport\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\ImportReport\Entities\CompetitorMapping;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrisyncVerticalReport extends Model
{
    
    
    protected $table = 'prisync_vertical_report';
    

    protected $fillable = ["product_name",
    "product_code",
    "barcode",
    "brand",
    "category",
    "product_tags",
    "number_of_matches",
    "index","position",
    "cheapest_site",
    "highest_site",
    "minimum_price",
    "maximum_price",
    "average_price",
    "my_price",
    "product_cost",
    "smart_price",
    "last_update_cycle",
    "site","site_index",
    "price",
    "change_direction",
    "stock"];

    public function competitor()
    {
        return $this->hasMany("Modules\ImportReport\Entities\CompetitorMapping", "mpn");
    }

}
