<?php

namespace Modules\Attribute\Repositories\Cache;

use Modules\Attribute\Repositories\AttributeOptionRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheAttributeOptionDecorator extends BaseCacheDecorator implements AttributeOptionRepository
{
    public function __construct(AttributeOptionRepository $attributeOption)
    {
        parent::__construct();
        $this->entityName = config("attribute.name");
        $this->repository = $attributeOption;
    }
}
