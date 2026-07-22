<?php

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{

	protected $table = "customer_address";

    protected $fillable = ['customer_id', 'street_name', 'building', 'unit_no', 'postal_code', 'tag', 'is_default_address'];

    //Please Don't remove below Line
    //AppendFunctionHere
}
