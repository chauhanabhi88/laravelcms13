<?php 
namespace Modules\Core\Handler;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class Log 
{
    CONST FILE_SIZE_LIMIT = 20;

    protected function renameFileOnSizeLimit($date, $module, $fileName) {
        if(file_exists(storage_path('logs/'.$date.'/'.$module.'/'.$fileName.'.log')) && (filesize(storage_path('logs/'.$date.'/'.$module.'/'.$fileName.'.log'))/1000000) > Log::FILE_SIZE_LIMIT) {
            $currentTime = date("His", time() + getTimezoneOffset());
            rename(storage_path('logs/'.$date.'/'.$module.'/'.$fileName.'.log'),storage_path('logs/'.$date.'/'.$module.'/'.$fileName.'_'.$currentTime.'.log'));
        }
    }

    public function generateLog($function = NULL, $message = NULL, $data = [], $logInfo = NULL)
    {
        // dd($logInfo);
        if(\config("core.is_filelog_enabled") && !empty($logInfo)) {
            $is_enabled = $logInfo['module'].'.'.$logInfo['file_name'].'.is_enabled';
            if(\config($is_enabled)) {

                $timezoneOffset = getTimezoneOffset();
                $currentDate = date("Y-m-d", time() + $timezoneOffset);

                $this->renameFileOnSizeLimit($currentDate, $logInfo["module"], $logInfo["file_name"]);

                $log = new Logger($logInfo['file_name']);

                if(file_exists(storage_path('logs/'.$currentDate.'/'.$logInfo['module'].'/'.$logInfo['file_name'].'.log'))) {

                    if( (!is_writable(storage_path('logs/'.$currentDate))) 
                    || (!is_readable(storage_path('logs/'.$currentDate)))
                    ||  (!is_executable(storage_path('logs/'.$currentDate))) ) {

                        chmod(storage_path('logs/'.$currentDate), 0777);
                    }

                    if( (!is_writable(storage_path('logs/'.$currentDate.'/'.$logInfo['module']))) 
                    || (!is_readable(storage_path('logs/'.$currentDate.'/'.$logInfo['module'])))
                    ||  (!is_executable(storage_path('logs/'.$currentDate.'/'.$logInfo['module']))) ) {

                        chmod(storage_path('logs/'.$currentDate.'/'.$logInfo['module']), 0777);
                    }

                    if( (!is_writable(storage_path('logs/'.$currentDate.'/'.$logInfo['module'].'/'.$logInfo['file_name'].'.log'))) 
                    || (!is_readable(storage_path('logs/'.$currentDate.'/'.$logInfo['module'].'/'.$logInfo['file_name'].'.log')))
                    ||  (!is_executable(storage_path('logs/'.$currentDate.'/'.$logInfo['module'].'/'.$logInfo['file_name'].'.log'))) ) {

                        chmod(storage_path('logs/'.$currentDate.'/'.$logInfo['module'].'/'.$logInfo['file_name'].'.log'), 0777); 
                    }

                    $log->pushHandler(new StreamHandler(storage_path('logs/'.$currentDate.'/'.$logInfo['module'].'/'.$logInfo['file_name'].'.log')));
                    $log->$function($message, $data);

                } else {
                    if(!is_dir(storage_path('logs/'.$currentDate))) {
                        \File::makeDirectory(storage_path('logs/'.$currentDate), 0777, true);
                    }
                    if(!is_dir(storage_path('logs/'.$currentDate.'/'.$logInfo['module']))) {
                        \File::makeDirectory(storage_path('logs/'.$currentDate.'/'.$logInfo['module']), 0777, true);
                    }
                    $log->pushHandler(new StreamHandler(storage_path('logs/'.$currentDate.'/'.$logInfo['module'].'/'.$logInfo['file_name'].'.log')));
                    $log->$function($message, $data);
                }
            }
        }

    }
}