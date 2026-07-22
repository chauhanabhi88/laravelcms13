<?php

namespace Modules\Settings\Repositories\Cache;

use Modules\Settings\Repositories\SettingsRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheSettingsDecorator extends BaseCacheDecorator implements SettingsRepository
{
    public function __construct(SettingsRepository $settings)
    {
        parent::__construct();
        $this->entityName = \Config::get("settings.name");;
        $this->repository = $settings;
    }

    public function getModuleSettings($module)
    {
        return $this->remember(function() use ($module) {
            return $this->repository->getModuleSettings($module);
        });
    }

    public function setEnvironmentValues(array $values)
    {
        return $this->repository->setEnvironmentValues($values);
    }
}