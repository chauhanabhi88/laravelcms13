<?php

namespace Modules\Language\Repositories\Cache;

use Modules\Language\Repositories\LanguageRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheLanguageDecorator extends BaseCacheDecorator implements LanguageRepository
{
    public function __construct(LanguageRepository $language)
    {
        parent::__construct();
        $this->entityName = \config("language.name");
        $this->repository = $language;
    }

    public function sortColumns($request)
    {
        return $this->repository->sortColumns($request);
    }

    public function getFilters($request, $statusOptions, $yesNoOptions)
    {
        return $this->repository->getFilters($request, $statusOptions, $yesNoOptions);
    }

    public function pagination($request)
    {
        return $this->remember(function() use ($request) {
            return $this->repository->pagination($request);
        });
    }

    public function filter($request)
    {
        return $this->remember(function() use ($request) {
            return $this->repository->filter($request);
        });
    }

    public function getLanguageOptions()
    {
        return $this->remember(function () {
            return $this->repository->getLanguageOptions();
        });
    }
    public function getTranslationOptions($flag = false)
    {
        return $this->remember(function () use ($flag){
            return $this->repository->getTranslationOptions($flag);
        });
    }
}
