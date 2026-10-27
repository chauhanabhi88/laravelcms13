<?php

declare(strict_types=1);

namespace Modules\Directory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\MarketplaceModel;
use Modules\Directory\Database\Factories\DirectoryPincodeFactory;

/**
 * One India Post office. Serviceability, COD and zone are pincode-level flags kept
 * in step across every office of a pincode (see DirectoryPincodeRepository::updateFlags()).
 *
 * @property int $id
 * @property string $pincode
 * @property string $office_name
 * @property string $district
 * @property int|null $state_id
 * @property int|null $city_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int $is_serviceable
 * @property int $cod_allowed
 * @property string|null $zone
 */
class DirectoryPincode extends MarketplaceModel
{
    /** @use HasFactory<DirectoryPincodeFactory> */
    use HasFactory;

    public const ZONES = ['metro', 'roi', 'ne_jk', 'special'];

    /**
     * Columns describing the pincode as a whole rather than one office.
     */
    public const PINCODE_LEVEL_FIELDS = ['is_serviceable', 'cod_allowed', 'zone'];

    protected $table = 'directory_pincode';

    protected $fillable = [
        'pincode',
        'office_name',
        'district',
        'state_id',
        'city_id',
        'latitude',
        'longitude',
        'is_serviceable',
        'cod_allowed',
        'zone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state_id' => 'integer',
            'city_id' => 'integer',
            'is_serviceable' => 'integer',
            'cod_allowed' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DirectoryCountryState, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(DirectoryCountryState::class, 'state_id');
    }

    /**
     * @return BelongsTo<DirectoryCountryCity, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(DirectoryCountryCity::class, 'city_id');
    }

    public function isServiceable(): bool
    {
        return $this->is_serviceable === (int) config('core.yes');
    }

    public function allowsCod(): bool
    {
        return $this->cod_allowed === (int) config('core.yes');
    }

    protected static function newFactory(): DirectoryPincodeFactory
    {
        return DirectoryPincodeFactory::new();
    }
}
