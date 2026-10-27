<?php

declare(strict_types=1);

namespace Modules\Storefront\Models;

use Modules\Core\Models\MarketplaceModel;

class HomeSectionTranslation extends MarketplaceModel
{
    protected $table = 'home_section_translation';

    protected $fillable = ['home_section_id', 'locale', 'title', 'subtitle'];

    protected static function booted(): void
    {
        static::saved(fn () => HomeSection::flushHomeCache());
    }
}
