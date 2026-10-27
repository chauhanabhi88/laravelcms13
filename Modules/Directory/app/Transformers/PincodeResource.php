<?php

declare(strict_types=1);

namespace Modules\Directory\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Directory\Models\DirectoryPincode;

/**
 * @mixin DirectoryPincode
 */
class PincodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pincode' => $this->pincode,
            'district' => $this->district,
            'city' => $this->city === null ? null : ['id' => $this->city->id, 'name' => $this->city->name],
            'state' => $this->state === null ? null : ['id' => $this->state->id, 'name' => $this->state->name, 'code' => $this->state->code],
            'is_serviceable' => $this->isServiceable(),
            'cod_allowed' => $this->isServiceable() && $this->allowsCod(),
            'zone' => $this->zone,
        ];
    }
}
