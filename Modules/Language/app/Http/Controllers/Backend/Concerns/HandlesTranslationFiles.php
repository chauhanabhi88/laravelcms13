<?php

namespace Modules\Language\Http\Controllers\Backend\Concerns;

use Nwidart\Modules\Facades\Module;

trait HandlesTranslationFiles
{
    /**
     * Resolve a raw module name against the list of actually installed modules,
     * returning the module's real (studly-case) directory name for filesystem use.
     *
     * @param  array  $installedModules  Keyed by lowercase module alias (e.g. from getModulesName()).
     */
    protected function resolveModuleName(string $raw, array $installedModules): ?string
    {
        $alias = strtolower($raw);

        if (! array_key_exists($alias, $installedModules)) {
            return null;
        }

        $module = Module::find($alias);

        return $module ? $module->getStudlyName() : null;
    }

    /**
     * Resolve a raw locale against the configured language options.
     */
    protected function resolveLocale(string $raw, array $languageOptions): ?string
    {
        return array_key_exists($raw, $languageOptions) ? $raw : null;
    }

    /**
     * Reduce a raw file name to a safe basename with no path segments.
     */
    protected function sanitizeFileName(string $raw): ?string
    {
        $base = basename($raw);

        if ($base !== $raw || ! preg_match('/^[A-Za-z0-9_]+$/', $base)) {
            return null;
        }

        return $base;
    }

    public function var_export54($var, $indent = '')
    {
        switch (gettype($var)) {
            case 'string':
                return '"'.addcslashes($var, "\\\$\"\r\n\t\v\f").'"';
            case 'array':
                $indexed = array_keys($var) === range(0, count($var) - 1);
                $r = [];
                foreach ($var as $key => $value) {
                    $r[] = "$indent    "
                        .($indexed ? '' : $this->var_export54($key).' => ')
                        .$this->var_export54($value, "$indent    ");
                }

                return "[\n".implode(",\n", $r)."\n".$indent.']';
            case 'boolean':
                return $var ? 'TRUE' : 'FALSE';
            default:
                return var_export($var, true);
        }
    }
}
