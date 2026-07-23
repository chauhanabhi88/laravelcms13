<?php

namespace Modules\Language\Repositories\Cache;

use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Language\Repositories\TranslationRepository;

class CacheTranslationDecorator extends BaseCacheDecorator implements TranslationRepository
{
    public function __construct(TranslationRepository $translation)
    {
        parent::__construct();
        $this->entityName = \config('language.name');
        $this->repository = $translation;
    }

    public function sortColumns()
    {
        return $this->repository->sortColumns();
    }

    public function getModules()
    {
        return $this->remember(function () {
            return $this->repository->getModules();
        });
    }

    public function getModulesName()
    {
        return $this->remember(function () {
            return $this->repository->getModulesName();
        });
    }
}
