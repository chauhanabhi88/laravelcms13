<?php

namespace Modules\Directory\Repositories\Cache;

use Modules\Directory\Repositories\DirectoryCurrencySetupRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
 
class CacheDirectoryCurrencySetupDecorator extends BaseCacheDecorator implements DirectoryCurrencySetupRepository
{
    public function __construct(DirectoryCurrencySetupRepository $directory)
    {
        parent::__construct();
        $this->entityName = \config("directory.cache.currency_setup");
        $this->repository = $directory;
    }

    public function getCountryCurrencySymbol($flag = false)
    {
        return $this->remember(function() use ($flag) {
            return $this->repository->getCountryCurrencySymbol($flag = false);
        });
    }

    public function getCurrencyOptions($flag = false)
    {
        return $this->remember(function() use ($flag) {
            return $this->repository->getCurrencyOptions($flag = false);
        });
    }

    public function getCurrencyCode($currency)
    {
        return $this->remember(function() use ($currency) {
            return $this->repository->getCurrencyCode($currency);
        });
    }

    public function getBaseCurrency()
    {
        return $this->remember(function() {
            return $this->repository->getBaseCurrency();
        });
    }

    public function getDisplayCurrency()
    {
        return $this->remember(function(){
            return $this->repository->getDisplayCurrency();
        });
    }

    public function getAllowedCurrencies()
    {
        return $this->remember(function(){
            return $this->repository->getAllowedCurrencies();
        });
    }

    public function getAllowedCurrenciesRow()
    {
        return $this->remember(function(){
            return $this->repository->getAllowedCurrenciesRow();
        });
    }

    public function getCurrencies()
    {
        return $this->remember(function(){
            return $this->repository->getCurrencies();
        });
    }
}
