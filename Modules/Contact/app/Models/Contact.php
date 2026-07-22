<?php

namespace Modules\Contact\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 'contact';
    protected $fillable = ["name","email","contact_number","content"];

    //Please Don't remove below Line
    //AppendFunctionHere
    
}
