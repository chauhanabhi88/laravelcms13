<?php

namespace Modules\Mail\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ProvidersEventServiceProvider;

class EventServiceProvider extends ProvidersEventServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Illuminate\Mail\Events\MessageSent' => [
            'Modules\Mail\Listeners\LogSendMail',
        ],
    ];
}