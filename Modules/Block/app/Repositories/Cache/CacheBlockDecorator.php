<?php

namespace Modules\Block\Repositories\Cache;

use Modules\Block\Repositories\BlockRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheBlockDecorator extends BaseCacheDecorator implements BlockRepository
{
    public function __construct(BlockRepository $block)
    {
        parent::__construct();
        $this->entityName = \Config::get("block.name");
        $this->repository = $block;
    }

    /**
     * Get Cached data for sortColumns
     * 
     * return array
     */
    public function sortColumns($request)
    {
        return $this->repository->sortColumns($request);
    }

    /**
     * Get Cached filders data
     * 
     * @param $request
     * @param $languageOptions
     * @param $statusOptions
     * return data
     */
    public function getFilters($request, $languageOptions, $statusOptions)
    {
        return $this->repository->getFilters($request, $languageOptions, $statusOptions);
    }

    /**
     * Get Cached Grid data
     * 
     * @param $request
     * return data
     */
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

    /**
     * Get Cached Block Content
     * 
     * @param $slug
     * @param $locale
     * return content
     */
    public function getBlockContent($slug, $locale)
    {
       //return $this->remember(function() use ($slug, $locale) {
            return $this->repository->getBlockContent($slug, $locale);
        //});
    }
}
