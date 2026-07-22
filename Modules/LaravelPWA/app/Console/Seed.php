<?php

namespace Modules\LaravelPWA\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class Seed extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'laravelpwa:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'wer';

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
        //
    }
}
