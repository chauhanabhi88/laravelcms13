<?php

namespace Modules\Directory\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Repositories\Transaltion\Translatable;

class DirectoryCountry extends Model
{
    use Translatable;
    protected $table = 'directory_country';
    protected $translationForeignKey = 'directory_country_id';
    protected $fillable = ['code','iso2_code','iso3_code','is_allowed_country'];
    public $translatedAttributes = ['name'];
    public $timestamps = true;

    public function getCountryCode($country = null)
    {
    	$countryCode = '';
        if($country){
            $countryCode = $this->where('name', 'like', '%' . trim($country) . '%')->pluck('code')->first();
        }
        return $countryCode;
    }
}
