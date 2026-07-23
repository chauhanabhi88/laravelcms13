<?php

namespace Modules\Settings\Repositories\Eloquent;

use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Settings\Repositories\SettingsRepository;

class EloquentSettingsRepository extends EloquentBaseRepository implements SettingsRepository
{
    public function getModuleSettings($module)
    {
        $attributes = [
            'name' => $module,
        ];

        return $this->findByAttributes($attributes);
    }

    public function setEnvironmentValues(array $values)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);

        if (count($values) > 0) {
            foreach ($values as $envKey => $envValue) {
                if ($envValue === '************') {
                    $envValue = env($envKey); // fetch existing value
                }

                // Prevent a single value from injecting extra KEY=VALUE lines into the .env file
                $envValue = str_replace(["\r", "\n"], '', (string) $envValue);

                $str .= "\n"; // In case the searched variable is in the last line without \n
                $keyPosition = strpos($str, "{$envKey}=");
                $endOfLinePosition = $keyPosition !== false ? strpos($str, "\n", $keyPosition) : false;
                $oldLine = ($keyPosition !== false && $endOfLinePosition !== false)
                    ? substr($str, $keyPosition, $endOfLinePosition - $keyPosition)
                    : '';

                // If key does not exist, add it
                if ($keyPosition === false || $endOfLinePosition === false || $oldLine === '') {
                    $str .= "{$envKey}={$envValue}\n";
                } else {
                    $str = str_replace($oldLine, "{$envKey}={$envValue}", $str);
                }
            }
        }

        $str = substr($str, 0, -1);
        if (! file_put_contents($envFile, $str)) {
            return false;
        }

        return true;
    }
}
