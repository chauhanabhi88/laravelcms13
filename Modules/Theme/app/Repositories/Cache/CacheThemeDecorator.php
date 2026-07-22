<?php

namespace Modules\Theme\Repositories\Cache;

use Modules\Theme\Repositories\ThemeRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheThemeDecorator extends BaseCacheDecorator implements ThemeRepository
{
    public function __construct(ThemeRepository $theme)
    {
        parent::__construct();
        $this->entityName = \Config::get("theme.name");
        $this->repository = $theme;
    }
}
