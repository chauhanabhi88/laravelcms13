<?php

declare(strict_types=1);

namespace Modules\Customer\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Models\CustomerAddress;

/**
 * @mixin CustomerAddress
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'landmark' => $this->landmark,
            'pincode' => $this->pincode,
            'city' => ['id' => $this->city_id, 'name' => $this->city_name],
            'state' => ['id' => $this->state_id, 'name' => $this->state_name],
            'tag' => $this->tag,
            'is_default' => $this->isDefault(),
            'summary' => $this->summary(),
        ];
    }
}
