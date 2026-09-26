<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Contracts\Configs\NotificationConfigInterface;
use AndyDefer\LaravelNotification\Contracts\Processors\NotificationSenderProcessorInterface;
use AndyDefer\LaravelNotification\Contracts\Repositories\FcmDeviceRepositoryInterface;
use AndyDefer\LaravelNotification\Contracts\Repositories\NotificationRepositoryInterface;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Repositories\FcmDeviceRepository;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ Config
        $this->app->singleton(
            abstract: NotificationConfig::class,
            concrete: function ($app) {
                return new NotificationConfig($app['config']);
            }
        );

        $this->app->bind(
            abstract: NotificationConfigInterface::class,
            concrete: NotificationConfig::class
        );

        // ✅ Repository — Notification
        $this->app->singleton(
            abstract: NotificationRepository::class,
            concrete: function ($app) {
                return new NotificationRepository;
            }
        );

        $this->app->bind(
            abstract: NotificationRepositoryInterface::class,
            concrete: NotificationRepository::class
        );

        // ✅ Repository — FcmDevice
        $this->app->singleton(
            abstract: FcmDeviceRepository::class,
            concrete: function ($app) {
                return new FcmDeviceRepository;
            }
        );

        $this->app->bind(
            abstract: FcmDeviceRepositoryInterface::class,
            concrete: FcmDeviceRepository::class
        );

        // ✅ Processor
        $this->app->singleton(
            abstract: NotificationSenderProcessor::class,
            concrete: function ($app) {
                return new NotificationSenderProcessor(
                    notificationRepository: $app->make(NotificationRepositoryInterface::class),
                    logger: $app->make(LoggerInterface::class),
                );
            }
        );

        $this->app->bind(
            abstract: NotificationSenderProcessorInterface::class,
            concrete: NotificationSenderProcessor::class
        );

        // ✅ Service
        $this->app->singleton(
            abstract: NotificationService::class,
            concrete: function ($app) {
                return new NotificationService(
                    notificationRepository: $app->make(NotificationRepositoryInterface::class),
                    senderProcessor: $app->make(NotificationSenderProcessorInterface::class),
                    uniqueTaskService: $app->make(UniqueTaskServiceInterface::class),
                    recurringTaskService: $app->make(RecurringTaskServiceInterface::class),
                    logger: $app->make(LoggerInterface::class),
                    hydration: $app->make(HydrationService::class),
                );
            }
        );

        $this->app->bind(
            abstract: NotificationServiceInterface::class,
            concrete: NotificationService::class
        );

        // ✅ NotifiableBuilder
        $this->app->singleton(
            abstract: NotifiableBuilder::class,
            concrete: function ($app) {
                return NotifiableBuilder::create();
            }
        );

        $this->app->alias(
            abstract: NotifiableBuilder::class,
            alias: 'notifiable.builder'
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/notification.php');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'notification-migrations');

        $this->publishes([
            __DIR__.'/../config/notification.php' => config_path('notification.php'),
        ], 'notification-config');

        $this->publishes([
            __DIR__.'/../routes/notification.php' => base_path('routes/notification.php'),
        ], 'notification-routes');
    }
}
