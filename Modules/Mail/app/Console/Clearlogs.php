<?php

namespace Modules\Mail\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Modules\Mail\Models\MailLog;
use Modules\Mail\Repositories\MailLogRepository;

class Clearlogs extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'mail:clearlogs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This will clear all mail logs.';

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
        MailLog::truncate();
        $mailLogRepo = app(MailLogRepository::class);
        $mailLogRepo->flushCache(config("mail.cache.mail_log"));
    }
}
