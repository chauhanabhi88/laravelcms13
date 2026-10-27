<?php

declare(strict_types=1);

namespace Modules\Storefront\Models;

use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * Nightly aggregate of the search log per term (storefront:aggregate-search-terms).
 *
 * @property string $term
 * @property int $searches_7d
 * @property int $searches_30d
 * @property int $avg_results
 * @property int $zero_result_count
 * @property int $clicks_30d
 * @property Carbon|null $updated_at
 */
class SearchTermStat extends MarketplaceModel
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'term';

    protected $table = 'search_term_stat';

    protected $fillable = ['term', 'searches_7d', 'searches_30d', 'avg_results', 'zero_result_count', 'clicks_30d', 'updated_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'searches_7d' => 'integer',
            'searches_30d' => 'integer',
            'avg_results' => 'integer',
            'zero_result_count' => 'integer',
            'clicks_30d' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Click-through rate in percent over the last 30 days.
     */
    public function clickThroughRate(): float
    {
        return $this->searches_30d > 0 ? round($this->clicks_30d * 100 / $this->searches_30d, 1) : 0.0;
    }
}
