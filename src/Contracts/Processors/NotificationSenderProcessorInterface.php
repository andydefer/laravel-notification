<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts\Processors;

use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for notification sender processors.
 *
 * Defines the contract for processing and sending notifications
 * through multiple channels with routing and error handling.
 */
interface NotificationSenderProcessorInterface
{
    /**
     * Send a notification through the specified channels.
     *
     * @param  NotifiableInterface&Model  $notifiable  The notifiable entity
     * @param  NotificationMessageVO  $message  The notification message
     * @param  ProcessNotificationRecord  $processRecord  The processing configuration
     * @return SendResultCollection Collection of send results
     *
     * @throws \RuntimeException If no channels are available
     */
    public function send(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        ProcessNotificationRecord $processRecord
    ): SendResultCollection;
}
