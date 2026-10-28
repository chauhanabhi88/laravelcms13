<?php

declare(strict_types=1);

namespace Modules\Notification\Providers;

use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Modules\Customer\Events\CustomerRegistered;
use Modules\Notification\Contracts\PushDriver;
use Modules\Notification\Contracts\WhatsAppDriver;
use Modules\Notification\Drivers\Fcm\FcmAccessToken;
use Modules\Notification\Drivers\Fcm\FcmPushDriver;
use Modules\Notification\Drivers\LogPushDriver;
use Modules\Notification\Drivers\NullWhatsAppDriver;
use Modules\Notification\Listeners\LogNotificationSent;
use Modules\Notification\Listeners\NotifyAdminsOfSellerSubmission;
use Modules\Notification\Listeners\SendCustomerWelcome;
use Modules\Notification\Listeners\SendProductModerationNotifications;
use Modules\Notification\Listeners\SendSellerDecisionNotifications;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Models\NotificationTemplate;
use Modules\Notification\Repositories\Cache\CacheNotificationLogDecorator;
use Modules\Notification\Repositories\Cache\CacheNotificationTemplateDecorator;
use Modules\Notification\Repositories\Eloquent\EloquentNotificationLogRepository;
use Modules\Notification\Repositories\Eloquent\EloquentNotificationTemplateRepository;
use Modules\Notification\Repositories\NotificationLogRepository;
use Modules\Notification\Repositories\NotificationTemplateRepository;
use Modules\Notification\Services\NotificationHub;
use Modules\Notification\Support\EventCatalogue;
use Modules\Notification\Support\ThemeNotificationBell;
use Modules\Product\Events\ProductApproved;
use Modules\Product\Events\ProductRejected;
use Modules\Seller\Events\SellerApproved;
use Modules\Seller\Events\SellerChangesRequested;
use Modules\Seller\Events\SellerRejected;
use Modules\Seller\Events\SellerSubmittedForVerification;
use Modules\Theme\Contracts\NotificationBellProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->registerListeners();
        $this->registerSellerMenu();
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->registerBindings();

        $this->app->singleton(EventCatalogue::class);
        $this->app->bind(NotificationHub::class);
        $this->app->bind(NotificationBellProvider::class, ThemeNotificationBell::class);
        $this->registerDrivers();
    }

    /**
     * Delivery logging for every hub channel, plus the existing domain events that now
     * reach recipients through the hub (later modules add their own listeners).
     */
    protected function registerListeners(): void
    {
        Event::listen([NotificationSent::class, NotificationFailed::class], LogNotificationSent::class);

        Event::listen(CustomerRegistered::class, SendCustomerWelcome::class);
        Event::listen([SellerApproved::class, SellerRejected::class, SellerChangesRequested::class], SendSellerDecisionNotifications::class);
        Event::listen(SellerSubmittedForVerification::class, NotifyAdminsOfSellerSubmission::class);
        Event::listen([ProductApproved::class, ProductRejected::class], SendProductModerationNotifications::class);
    }

    /**
     * Push (log | fcm) and WhatsApp (null) drivers. Singletons, so a test that swaps in a
     * fake sees every message.
     */
    protected function registerDrivers(): void
    {
        $this->app->singleton(PushDriver::class, function ($app): PushDriver {
            $driver = (string) $app['config']->get('notification.push.driver', 'log');

            return match ($driver) {
                'log' => new LogPushDriver,
                'fcm' => new FcmPushDriver(
                    (array) $app['config']->get('notification.push.fcm', []),
                    new FcmAccessToken((array) $app['config']->get('notification.push.fcm', [])),
                ),
                default => throw new InvalidArgumentException("Unsupported push driver [{$driver}]."),
            };
        });

        $this->app->singleton(WhatsAppDriver::class, function ($app): WhatsAppDriver {
            $driver = (string) $app['config']->get('notification.whatsapp.driver', 'null');

            return match ($driver) {
                'null', 'log' => new NullWhatsAppDriver,
                default => throw new InvalidArgumentException("Unsupported WhatsApp driver [{$driver}]."),
            };
        });
    }

    /**
     * "Notifications" under the supplier panel's settings section.
     */
    protected function registerSellerMenu(): void
    {
        $menu = (array) config('theme.seller_menu', []);
        $menu['settings']['label'] ??= 'seller::panel.menu.settings';
        $menu['settings']['items'][] = [
            'route' => 'seller.notification_preferences.edit',
            'label' => 'notification::front.preferences.menu',
            'icon' => 'bell',
            'active' => 'seller.notification_preferences.*',
        ];

        config(['theme.seller_menu' => $menu]);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/config.php' => config_path('modules/notification.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'notification');
        $this->mergeConfigFrom(__DIR__.'/../../config/events.php', 'notification.events');
    }

    protected function registerViews(): void
    {
        $sourcePath = __DIR__.'/../../resources/views';

        $this->publishes([
            $sourcePath => resource_path('views/modules/notification'),
        ], 'views');

        $this->loadViewsFrom([$sourcePath], 'notification');
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/notification');

        $this->loadTranslationsFrom(is_dir($langPath) ? $langPath : __DIR__.'/../../resources/lang', 'notification');
    }

    /**
     * Repositories bound to Eloquent, wrapped in the cache decorator when the module's
     * "cache" flag (module.json) is on.
     */
    protected function registerBindings(): void
    {
        $repositories = [
            NotificationTemplateRepository::class => [EloquentNotificationTemplateRepository::class, NotificationTemplate::class, CacheNotificationTemplateDecorator::class],
            NotificationLogRepository::class => [EloquentNotificationLogRepository::class, NotificationLog::class, CacheNotificationLogDecorator::class],
        ];

        foreach ($repositories as $contract => [$eloquent, $model, $decorator]) {
            $this->app->bind($contract, function () use ($eloquent, $model, $decorator) {
                $repository = new $eloquent(new $model);

                if (! getModule('notification', 'cache')) {
                    return $repository;
                }

                return new $decorator($repository);
            });
        }
    }
}
