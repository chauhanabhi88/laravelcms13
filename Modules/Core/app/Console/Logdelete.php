<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use ZipArchive;

class Logdelete extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'core:logdelete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create zip for all logs generated at the end of month and then delete it.0 0 1 * *';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $destination = storage_path('log_archive');
        if (!is_dir($destination)) {
            \File::makeDirectory($destination, 0777, true, true);
        }
        $month = getFormatedDate(time(), "m");
        $year = getFormatedDate(time(), "Y");
        if ($month == 1) {
            $month = 12;
            $year = $year - 1;
        } else {
            $month = $month - 1;
        }
        $saveFolder = $destination . '/' . $month . '-' . $year;
        $path = base_path() . '/storage/logs';
        $file = $destination . '/' . ($month - 1) . '-' . $year . '.zip';
        if (is_file($file)) {
            unlink($file);
        }
        if (!is_dir($path)) {
            return false;
            exit();
        }
        $logDir = array_diff(scandir($path), array('.', '..'));
        if (!empty($logDir)) {
            foreach ($logDir as $fileinfo) {
                if (($fileinfo == '.gitignore') || ($fileinfo == 'workers')) {
                    continue;
                }
                $dir = new \SplFileInfo($path . '/' . $fileinfo);
                if ($month == date('m', $dir->getMTime())) {
                    if (!is_dir($saveFolder)) {
                        \File::makeDirectory($saveFolder, 0777, true, true);
                    }
                    if (is_dir($path . '/' . $fileinfo)) {
                        if (!is_dir($saveFolder . '/' . $fileinfo)) {
                            \File::makeDirectory($saveFolder . '/' . $fileinfo, 0777, true, true);
                        }
                    }
                    rename($path . '/' . $fileinfo, $saveFolder . '/' . $fileinfo);
                }
            }


            if (is_dir($saveFolder)) {
                $rootPath = realpath($saveFolder);
                // Initialize archive object
                $zip = new ZipArchive();
                $zip->open($saveFolder . '.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

                // Create recursive directory iterator
                /** @var SplFileInfo[] $files */
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($rootPath),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    // Skip directories (they would be added automatically)
                    if (!$file->isDir()) {
                        // Get real and relative path for current file
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($rootPath) + 1);

                        // Add current file to archive
                        $zip->addFile($filePath, $relativePath);
                    }
                }

                // Zip archive will be created only after closing object
                $zip->close();
                $it = new \RecursiveDirectoryIterator($saveFolder, \RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new \RecursiveIteratorIterator(
                    $it,
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $file) {
                    if ($file->isDir()) {
                        rmdir($file->getRealPath());
                    } else {
                        unlink($file->getRealPath());
                    }
                }
                rmdir($saveFolder);
            }
        }
    }
}
