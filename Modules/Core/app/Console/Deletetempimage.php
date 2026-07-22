<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Filesystem\Filesystem;

class Deletetempimage extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'core:deletetempimage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'this cron use for remove temp image of summernote';

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
        if(is_dir(public_path('storage') . '/' . \Config::get('core.summernote_temp_folder_name'))) {

            if( (!is_writable(public_path('storage') . '/' . \Config::get('core.summernote_temp_folder_name'))) 
            || (!is_readable(public_path('storage') . '/' . \Config::get('core.summernote_temp_folder_name')))
            || (!is_executable(public_path('storage') . '/' . \Config::get('core.summernote_temp_folder_name'))) ) {
                chmod(public_path('storage') . '/' . \Config::get('core.summernote_temp_folder_name'), 0777);
            }

            $file = new Filesystem;
            $file->cleanDirectory(public_path('storage') . '/' . \Config::get('core.summernote_temp_folder_name'));

        }
    }
}
