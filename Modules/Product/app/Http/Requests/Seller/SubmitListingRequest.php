<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Seller;

/**
 * Wizard review: send the draft (or a rejected product) to QC.
 */
class SubmitListingRequest extends SellerProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
