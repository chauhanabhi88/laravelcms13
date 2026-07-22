<?php

namespace Modules\Cron\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class Test extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cron:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'To test cron';

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
        \Log::info("Test cron");
    }
}
