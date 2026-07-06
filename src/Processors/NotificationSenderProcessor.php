<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Processors;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Contracts\Processors\NotificationSenderProcessorInterface;
use AndyDefer\LaravelNotification\Contracts\Repositories\NotificationRepositoryInterface;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\Records\NotificationRecord;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;
use AndyDefer\LaravelNotification\Records\SendResultRecord;
use AndyDefer\LaravelNotification\ValueObjects\ErrorMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\LaravelNotification\ValueObjects\UuidVO;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Records\LogDataRecord;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Processor for sending notifications.
 *
 * Orchestrates the notification sending process by resolving routes,
 * creating notifications, and dispatching through appropriate drivers.
 */
final class NotificationSenderProcessor implements NotificationSenderProcessorInterface
{
    /**
     * Constructor for the notification sender processor.
     *
     * @param  NotificationRepositoryInterface  $notificationRepository  The notification repository
     * @param  LoggerInterface  $logger  The logger instance
     */
    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function send(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        ProcessNotificationRecord $processRecord
    ): SendResultCollection {
        $availableRoutes = $notifiable->getNotificationChannels();

        $routes = $this->resolveRoutes($processRecord->channels, $availableRoutes);

        if ($routes->isEmpty()) {
            throw new RuntimeException(
                sprintf(
                    'No available channels for notifiable %s#%d',
                    $notifiable->getMorphClass(),
                    $notifiable->getKey()
                )
            );
        }

        $routes = $this->applyLimitPerChannel($routes, $processRecord->limit_per_channel);

        if ($routes->isEmpty()) {
            throw new RuntimeException(
                sprintf(
                    'No routes after applying limit for notifiable %s#%d',
                    $notifiable->getMorphClass(),
                    $notifiable->getKey()
                )
            );
        }

        $sessionId = UuidVO::generate();
        $results = new SendResultCollection;

        foreach ($routes as $route) {
            $notification = $this->createNotification(
                $notifiable,
                $message,
                $route,
                $sessionId
            );

            $result = $this->sendViaDriver($notification, $message, $route, $notifiable, $sessionId);
            $results->add($result);
        }

        return $results;
    }

    /**
     * Send a notification via a specific driver.
     *
     * @param  Notification  $notification  The notification record
     * @param  NotificationMessageVO  $message  The notification message
     * @param  NotificationRouteVO  $route  The notification route
     * @param  NotifiableInterface&Model  $notifiable  The notifiable entity
     * @param  UuidVO  $sessionId  The session ID
     * @return SendResultRecord The send result
     */
    private function sendViaDriver(
        Notification $notification,
        NotificationMessageVO $message,
        NotificationRouteVO $route,
        NotifiableInterface&Model $notifiable,
        UuidVO $sessionId
    ): SendResultRecord {
        try {
            $driver = $route->createDriver();
            $result = $driver->send($message, $route);

            $this->notificationRepository->update($notification->getId(), NotificationRecord::from([
                'status' => NotificationStatus::SENT,
                'sent_at' => now(),
            ]));

            return $result;
        } catch (\Exception $e) {
            $this->logChannelFailure($route, $notifiable, $sessionId, $e);

            $this->notificationRepository->update($notification->getId(), NotificationRecord::from([
                'status' => NotificationStatus::FAILED,
                'error' => $e->getMessage(),
            ]));

            return new SendResultRecord(
                channel: new FqcnChannelVO($route->getChannelClass()),
                destination: $route->getDestination(),
                success: false,
                error_message: ErrorMessageVO::from($e->getMessage()),
            );
        }
    }

    /**
     * Resolve the routes to use based on requested channels.
     *
     * @param  FqcnChannelCollection  $channels  The requested channels
     * @param  NotificationRouteCollection  $availableRoutes  The available routes
     * @return NotificationRouteCollection The resolved routes
     */
    private function resolveRoutes(
        FqcnChannelCollection $channels,
        NotificationRouteCollection $availableRoutes
    ): NotificationRouteCollection {
        if ($channels->isEmpty()) {
            return $availableRoutes;
        }

        $filteredRoutes = new NotificationRouteCollection;

        foreach ($channels as $fqcnVO) {
            $channelClass = $fqcnVO->getValue();
            foreach ($availableRoutes as $route) {
                if ($route->getChannelClass() === $channelClass) {
                    $filteredRoutes->add($route);
                }
            }
        }

        return $filteredRoutes;
    }

    /**
     * Apply limit per channel to the routes.
     *
     * @param  NotificationRouteCollection  $routes  The routes to limit
     * @param  int|null  $limitPerChannel  The maximum number of routes per channel
     * @return NotificationRouteCollection The limited routes
     */
    private function applyLimitPerChannel(
        NotificationRouteCollection $routes,
        ?int $limitPerChannel
    ): NotificationRouteCollection {
        if ($limitPerChannel === null || $limitPerChannel <= 0) {
            return $routes;
        }

        $limitedRoutes = new NotificationRouteCollection;
        $channelCounters = [];

        foreach ($routes as $route) {
            $channelClass = $route->getChannelClass();

            if (! isset($channelCounters[$channelClass])) {
                $channelCounters[$channelClass] = 0;
            }

            if ($channelCounters[$channelClass] < $limitPerChannel) {
                $limitedRoutes->add($route);
                $channelCounters[$channelClass]++;
            }
        }

        return $limitedRoutes;
    }

    /**
     * Create a notification record.
     *
     * @param  NotifiableInterface&Model  $notifiable  The notifiable entity
     * @param  NotificationMessageVO  $message  The notification message
     * @param  NotificationRouteVO  $route  The notification route
     * @param  UuidVO  $sessionId  The session ID
     * @return Notification The created notification
     */
    private function createNotification(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        NotificationRouteVO $route,
        UuidVO $sessionId
    ): Notification {
        $record = NotificationRecord::from([
            'id' => UuidVO::generate(),
            'session_id' => $sessionId,
            'channel' => new FqcnChannelVO($route->getChannelClass()),
            'destination' => $route->getDestination(),
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'message' => $message,
            'metadata' => $route->getMetadata(),
            'status' => NotificationStatus::PENDING,
        ]);

        return $this->notificationRepository->create($record);
    }

    /**
     * Log a channel failure.
     *
     * @param  NotificationRouteVO  $route  The notification route
     * @param  NotifiableInterface&Model  $notifiable  The notifiable entity
     * @param  UuidVO  $sessionId  The session ID
     * @param  \Exception  $e  The exception
     */
    private function logChannelFailure(
        NotificationRouteVO $route,
        NotifiableInterface&Model $notifiable,
        UuidVO $sessionId,
        \Exception $e
    ): void {
        $payload = StrictDataObject::from([
            'event' => 'channel_failed',
            'channel' => $route->getChannelClass(),
            'destination' => $route->getDestination(),
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'session_id' => $sessionId->getValue(),
            'error' => $e->getMessage(),
        ]);

        $this->logger->error(new LogDataRecord(
            type: 'notification',
            payload: $payload
        ));
    }
}
