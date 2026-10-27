<?php

declare(strict_types=1);

namespace Modules\Storefront\Models;

use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * One first-page storefront search (FR-DSC-012), written after the response is sent.
 *
 * @property int $id
 * @property string $term
 * @property int $results_count
 * @property int|null $customer_id
 * @property string|null $session_id
 * @property int|null $clicked_product_id
 * @property Carbon|null $created_at
 */
class SearchQueryLog extends MarketplaceModel
{
    public const UPDATED_AT = null;

    protected $table = 'search_query_log';

    protected $fillable = ['term', 'results_count', 'customer_id', 'session_id', 'clicked_product_id', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'results_count' => 'integer',
            'customer_id' => 'integer',
            'clicked_product_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Lower case, single spaces, at most 120 characters (the column size).
     */
    public static function normaliseTerm(?string $term): string
    {
        $term = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $term)));

        return mb_substr($term, 0, 120);
    }
}
