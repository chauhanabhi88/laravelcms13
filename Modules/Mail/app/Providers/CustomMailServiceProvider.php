<?php

namespace Modules\Mail\Providers;
use Modules\Mail\Http\Controllers\Mailer;


class CustomMailServiceProvider extends \Illuminate\Mail\MailServiceProvider
{
	public function register()
	{
		$this->registerSwiftMailer();

		$this->app->singleton('mailer', function () {
            $config = $this->app->make('config')->get('mail');

            // Once we have create the mailer instance, we will set a container instance
            // on the mailer. This allows us to resolve mailer classes via containers
            // for maximum testability on said classes instead of passing Closures.
            $mailer = new Mailer(
                $this->app['view'],
                $this->app['swift.mailer'],
                $this->app['events']
            );

            if ($this->app->bound('queue')) {
                $mailer->setQueue($this->app['queue']);
            }

            // Next we will set all of the global addresses on this mailer, which allows
            // for easy unification of all "from" addresses as well as easy debugging
            // of sent messages since they get be sent into a single email address.
            foreach (['from', 'reply_to', 'to'] as $type) {
                $this->setGlobalAddress($mailer, $config, $type);
            }

            return $mailer;
        });
	}
}