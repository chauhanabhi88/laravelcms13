<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Models\Product;
use Modules\Seller\Support\CurrentSeller;

/**
 * Base for supplier panel requests about one product ({product} route parameter). The
 * tenancy check runs in authorize(), so another seller's product answers 404 before any
 * validation error could confirm it exists.
 */
abstract class SellerProductRequest extends FormRequest
{
    protected ?Product $resolvedProduct = null;

    public function authorize(): bool
    {
        if ($this->route('product') === null) {
            return true;
        }

        Gate::forUser(app(CurrentSeller::class)->user())->authorize('manage', $this->product());

        return true;
    }

    public function product(): Product
    {
        return $this->resolvedProduct ??= Product::query()->findOrFail((int) $this->route('product'));
    }

    public function hasProduct(): bool
    {
        return $this->route('product') !== null;
    }
}
