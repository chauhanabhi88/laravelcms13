<?php

declare(strict_types=1);

namespace Modules\Product\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * ProductData failed the catalog rules. Errors are keyed like request fields
 * ("variants.2.price", "catalog_category_id"), so a controller can flash them as-is.
 */
class ProductValidationException extends ProductException
{
    /**
     * @param  array<string, string>  $errors  field => message
     */
    public function __construct(protected array $errors)
    {
        parent::__construct((string) (reset($errors) ?: trans('product::product.messages.invalid_product')));
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages($this->errors);
    }
}
