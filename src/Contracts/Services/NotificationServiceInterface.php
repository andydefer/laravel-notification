<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts\Services;

use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Records\SendAtRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\Records\SessionStatsRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationStatsVO;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use Illuminate\Database\Eloquent\Model;

interface NotificationServiceInterface
{
    /**
     * Send a notification immediately.
     *
     * @param  NotifiableInterface&Model  $notifiable  The notifiable entity
     * @param  NotificationMessageVO  $message  The notification message
     * @param  SendNowRecord|null  $record  The send configuration
     * @return SendResultCollection The collection of send results
     */
    public function sendNow(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        ?SendNowRecord $record = null
    ): SendResultCollection;

    /**
     * Send a notification after a delay.
     *
     * @param  NotifiableInterface&Model  $notifiable  The notifiable entity
     * @param  NotificationMessageVO  $message  The notification message
     * @param  SendLaterRecord|null  $record  The send configuration
     * @return TaskAliasVO The task alias
     */
    public function sendLater(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        ?SendLaterRecord $record = null
    ): TaskAliasVO;

    /**
     * Send a notification at a specific time.
     *
     * @param  NotifiableInterface&Model  $notifiable  The notifiable entity
     * @param  NotificationMessageVO  $message  The notification message
     * @param  SendAtRecord|null  $record  The send configuration
     * @return TaskAliasVO The task alias
     */
    public function sendAt(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        ?SendAtRecord $record = null
    ): TaskAliasVO;

    /**
     * Send a notification on a recurring schedule.
     *
     * @param  NotifiableInterface&Model  $notifiable  The notifiable entity
     * @param  NotificationMessageVO  $message  The notification message
     * @param  SendRecurringRecord|null  $record  The send configuration
     * @return TaskAliasVO The task alias
     */
    public function sendRecurring(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        ?SendRecurringRecord $record = null
    ): TaskAliasVO;

    /**
     * Cancel a scheduled notification task.
     *
     * @param  string  $signature  The task alias
     * @return bool True if the task was cancelled
     */
    public function cancel(string $signature): bool;

    /**
     * Pause a recurring notification.
     *
     * @param  string  $signature  The task alias
     * @return bool True if the task was paused
     */
    public function pause(string $signature): bool;

    /**
     * Resume a paused recurring notification.
     *
     * @param  string  $signature  The task alias
     * @return bool True if the task was resumed
     */
    public function resume(string $signature): bool;

    /**
     * Change the interval of a recurring notification.
     *
     * @param  string  $signature  The task alias
     * @param  int  $newIntervalSeconds  The new interval in seconds
     * @return bool True if the interval was changed
     */
    public function changeInterval(string $signature, int $newIntervalSeconds): bool;

    /**
     * Get statistics for a notifiable entity.
     *
     * @param  NotifiableInterface&Model  $notifiable  The notifiable entity
     * @return NotificationStatsVO The statistics
     */
    public function getStats(NotifiableInterface&Model $notifiable): NotificationStatsVO;

    /**
     * Get statistics for a notification session.
     *
     * @param  string  $sessionId  The session ID
     * @return SessionStatsRecord The session statistics
     */
    public function getSessionStats(string $sessionId): SessionStatsRecord;

    /**
     * Set options for the next send operation.
     *
     * @param  SendOptions  $options  The send options
     * @return $this
     */
    public function withOptions(SendOptions $options): self;

    /**
     * Reset the pending options.
     *
     * @return $this
     */
    public function resetOptions(): self;
}
