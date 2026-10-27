<?php

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Customer\Database\Factories\CustomerAddressFactory;

/**
 * A saved delivery address. The M08 shape (name, phone, address_line1/2, landmark, pincode,
 * city/state) is primary; the legacy columns (building, unit_no, street_name, postal_code)
 * are kept in step on save because the admin customer screen and the legacy API still use them.
 *
 * is_default_address keeps its 1/0 semantics (documented exception to the 1/2 convention).
 */
class CustomerAddress extends Model
{
    use HasFactory;

    public const TAGS = ['home', 'work', 'other'];

    protected $table = 'customer_address';

    protected $fillable = [
        'customer_id', 'street_name', 'building', 'unit_no', 'postal_code', 'tag', 'is_default_address',
        'name', 'phone', 'address_line1', 'address_line2', 'landmark', 'pincode',
        'city_id', 'city_name', 'state_id', 'state_name', 'latitude', 'longitude',
    ];

    protected $casts = [
        'city_id' => 'integer',
        'state_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (CustomerAddress $address): void {
            $address->syncLegacyColumns();
        });
    }

    public function isDefault(): bool
    {
        return (int) $this->is_default_address === (int) config('customer.is_default_address.yes');
    }

    /**
     * One-line summary for cards and dropdowns.
     */
    public function summary(): string
    {
        return collect([
            $this->address_line1,
            $this->address_line2,
            $this->landmark,
            $this->city_name,
            trim($this->state_name.' '.$this->pincode),
        ])->map(fn ($part) => trim((string) $part))->filter()->implode(', ');
    }

    /**
     * Copy whichever shape was written into the other one, so both stay readable.
     */
    protected function syncLegacyColumns(): void
    {
        $newShapeWritten = $this->isDirty(['address_line1', 'address_line2', 'pincode']);
        $legacyWritten = $this->isDirty(['building', 'unit_no', 'street_name', 'postal_code']);

        if ($newShapeWritten || ! $legacyWritten) {
            if ($this->isDirty('address_line1') && ! $this->isDirty(['building', 'unit_no'])) {
                $this->building = $this->address_line1;
                $this->unit_no = null;
            }
            if ($this->isDirty('address_line2') && ! $this->isDirty('street_name')) {
                $this->street_name = $this->address_line2;
            }
            if ($this->isDirty('pincode') && ! $this->isDirty('postal_code')) {
                $this->postal_code = $this->pincode;
            }

            return;
        }

        if ($this->isDirty(['building', 'unit_no'])) {
            $line1 = collect([$this->unit_no, $this->building])->map(fn ($part) => trim((string) $part))->filter()->implode(', ');
            $this->address_line1 = $line1 === '' ? null : mb_substr($line1, 0, 200);
        }
        if ($this->isDirty('street_name')) {
            $this->address_line2 = blank($this->street_name) ? null : mb_substr(trim((string) $this->street_name), 0, 200);
        }
        if ($this->isDirty('postal_code')) {
            $postalCode = trim((string) $this->postal_code);
            $this->pincode = preg_match('/^[1-9][0-9]{5}$/', $postalCode) === 1 ? $postalCode : null;
        }
    }

    // Please Don't remove below Line
    // AppendFunctionHere

    protected static function newFactory(): CustomerAddressFactory
    {
        return CustomerAddressFactory::new();
    }
}
