<?php

namespace Modules\Core\Handler;

use Illuminate\Support\Facades\File;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class Log
{
    const FILE_SIZE_LIMIT = 20;

    private const ALLOWED_LOG_METHODS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    protected function renameFileOnSizeLimit($date, $module, $fileName)
    {
        $logFile = storage_path('logs/'.$date.'/'.$module.'/'.$fileName.'.log');

        if (file_exists($logFile) && (filesize($logFile) / 1000000) > self::FILE_SIZE_LIMIT) {
            $currentTime = date('His', time() + getTimezoneOffset());
            rename($logFile, storage_path('logs/'.$date.'/'.$module.'/'.$fileName.'_'.$currentTime.'.log'));
        }
    }

    public function generateLog($function = null, $message = null, $data = [], $logInfo = null)
    {
        if (! \config('core.is_filelog_enabled') || empty($logInfo)) {
            return;
        }

        if (! in_array($function, self::ALLOWED_LOG_METHODS, true)) {
            return;
        }

        $module = preg_replace('/[^A-Za-z0-9_-]/', '', $logInfo['module'] ?? '');
        $fileName = preg_replace('/[^A-Za-z0-9_-]/', '', $logInfo['file_name'] ?? '');

        if ($module === '' || $fileName === '') {
            return;
        }

        if (! \config($module.'.'.$fileName.'.is_enabled')) {
            return;
        }

        $timezoneOffset = getTimezoneOffset();
        $currentDate = date('Y-m-d', time() + $timezoneOffset);

        $this->renameFileOnSizeLimit($currentDate, $module, $fileName);

        $dateDir = storage_path('logs/'.$currentDate);
        $moduleDir = $dateDir.'/'.$module;
        $logFile = $moduleDir.'/'.$fileName.'.log';

        if (file_exists($logFile)) {

            if ((! is_writable($dateDir))
            || (! is_readable($dateDir))
            || (! is_executable($dateDir))) {

                chmod($dateDir, 0777);
            }

            if ((! is_writable($moduleDir))
            || (! is_readable($moduleDir))
            || (! is_executable($moduleDir))) {

                chmod($moduleDir, 0777);
            }

            if ((! is_writable($logFile))
            || (! is_readable($logFile))) {

                chmod($logFile, 0777);
            }

        } else {
            if (! is_dir($dateDir)) {
                File::makeDirectory($dateDir, 0777, true, true);
            }
            if (! is_dir($moduleDir)) {
                File::makeDirectory($moduleDir, 0777, true, true);
            }
        }

        $log = new Logger($fileName);
        $log->pushHandler(new StreamHandler($logFile, Level::Debug, true, null, true));
        $log->$function($message, $data);
    }
}

