<?php

namespace Modules\Directory\Repositories\Cache;

use Modules\Directory\Repositories\DirectoryCurrencyRateRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
 
class CacheDirectoryCurrencyRateDecorator extends BaseCacheDecorator implements DirectoryCurrencyRateRepository
{
    public function __construct(DirectoryCurrencyRateRepository $directory)
    {
        parent::__construct();
        $this->entityName = \config("directory.cache.currency_rate");
        $this->repository = $directory;
    }

    public function getCountryCurrencyRate($flag = false)
    {
        return $this->remember(function() use ($flag) {
            return $this->repository->getCountryCurrencyRate($flag = false);
        });
    }

    public function getAllowedCurrenciesRate()
    {
        return $this->remember(function(){
            return $this->repository->getAllowedCurrenciesRate();
        });
    }
}
