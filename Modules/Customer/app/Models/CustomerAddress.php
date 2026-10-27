<?php

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Customer\Database\Factories\CustomerAddressFactory;

class CustomerAddress extends Model
{
    use HasFactory;

    protected $table = 'customer_address';

    protected $fillable = ['customer_id', 'street_name', 'building', 'unit_no', 'postal_code', 'tag', 'is_default_address'];

    // Please Don't remove below Line
    // AppendFunctionHere

    protected static function newFactory(): CustomerAddressFactory
    {
        return CustomerAddressFactory::new();
    }
}
