<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
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
        // ✅ Repository
        $this->app->singleton(
            abstract: NotificationRepository::class,
            concrete: NotificationRepository::class
        );

        // ✅ Processor
        $this->app->singleton(
            abstract: NotificationSenderProcessor::class,
            concrete: function ($app) {
                return new NotificationSenderProcessor(
                    notificationRepository: $app->make(NotificationRepository::class),
                    logger: $app->make(LoggerInterface::class),
                );
            }
        );

        // ✅ NotificationService (interface + concrete)
        $this->app->singleton(
            abstract: NotificationServiceInterface::class,
            concrete: function ($app) {
                return new NotificationService(
                    notificationRepository: $app->make(NotificationRepository::class),
                    senderProcessor: $app->make(NotificationSenderProcessor::class),
                    uniqueTaskService: $app->make(UniqueTaskServiceInterface::class),
                    recurringTaskService: $app->make(RecurringTaskServiceInterface::class),
                    logger: $app->make(LoggerInterface::class),
                    hydration: $app->make(HydrationService::class),
                );
            }
        );

        // ✅ Alias pour le service concret
        $this->app->alias(
            abstract: NotificationServiceInterface::class,
            alias: NotificationService::class
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'notification-migrations');

        $this->publishes([
            __DIR__.'/../config/notification.php' => config_path('notification.php'),
        ], 'notification-config');
    }
}
