<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;
use AndyDefer\LaravelNotification\Contracts\Processors\NotificationSenderProcessorInterface;
use AndyDefer\LaravelNotification\Contracts\Repositories\NotificationRepositoryInterface;
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
        // ✅ Repository - Bind interface to concrete implementation
        $this->app->singleton(
            abstract: NotificationRepositoryInterface::class,
            concrete: NotificationRepository::class
        );

        // ✅ Processor - Bind interface to concrete implementation
        $this->app->singleton(
            abstract: NotificationSenderProcessorInterface::class,
            concrete: function ($app) {
                return new NotificationSenderProcessor(
                    notificationRepository: $app->make(NotificationRepositoryInterface::class),
                    logger: $app->make(LoggerInterface::class),
                );
            }
        );

        // ✅ NotificationService (interface + concrete)
        $this->app->singleton(
            abstract: NotificationServiceInterface::class,
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

        // ✅ Alias for concrete service
        $this->app->alias(
            abstract: NotificationServiceInterface::class,
            alias: NotificationService::class
        );

        // ✅ NotifiableBuilder - Register as singleton
        $this->app->singleton(
            abstract: NotifiableBuilder::class,
            concrete: function ($app) {
                return NotifiableBuilder::create(
                );
            }
        );

        // ✅ Alias for NotifiableBuilder (convenience)
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

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'notification-migrations');

        $this->publishes([
            __DIR__.'/../config/notification.php' => config_path('notification.php'),
        ], 'notification-config');
    }
}
