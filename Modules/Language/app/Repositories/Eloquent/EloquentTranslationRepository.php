<?php

namespace Modules\Language\Repositories\Eloquent;

use Illuminate\Http\Request;
use Modules\Language\Repositories\TranslationRepository;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Language\Repositories\Repository;
use Nwidart\Modules\Facades\Module;

class EloquentTranslationRepository extends EloquentBaseRepository implements TranslationRepository
{
    public function sortColumns()
    {
        $columns = [
            [
                "title" => trans("core::core.titles.id"),
                "column" => "id",
                "default_sort" => true,
            ],
            [
                "title" => trans("language::language.titles.title"),
                "column" => "title",
            ],
            [
                "title" => trans("language::language.titles.locale"),
                "column" => "locale",
            ],
            [
                "title" => trans("language::language.titles.is_default"),
                "column" => "is_default",
            ],
        ];

        return $columns;
    }


    public function getModules()
    {
        // if (!getModule("core", "cache")) {
        //     $collection = Module::getCached();
        // } else {
            $collection = Module::toCollection()->toArray();
        //}
        return $collection;
    }


    public function getModulesName()
    {
        $collection = $this->getModules();
        $modules = [];
        $modules[''] = '-- Select Module --';
        foreach ($collection as $name => $value) {
            $modules[$name] = $name;
        }
        ksort($modules);
        return $modules;
    }
}
