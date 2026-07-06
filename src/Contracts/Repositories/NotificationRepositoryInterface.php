<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts\Repositories;

use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\Records\NotificationRecord;
use AndyDefer\Repository\AbstractRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for the notification repository.
 *
 * @extends AbstractRepositoryInterface<Notification, NotificationRecord>
 */
interface NotificationRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Mark a notification as read.
     *
     * @param  string  $id  The notification ID
     * @return bool True if the notification was marked as read
     */
    public function markAsRead(string $id): bool;

    /**
     * Mark a notification as delivered.
     *
     * @param  string  $id  The notification ID
     * @return bool True if the notification was marked as delivered
     */
    public function markAsDelivered(string $id): bool;

    /**
     * Mark a notification as sent.
     *
     * @param  string  $id  The notification ID
     * @return bool True if the notification was marked as sent
     */
    public function markAsSent(string $id): bool;

    /**
     * Mark a notification as failed.
     *
     * @param  string  $id  The notification ID
     * @param  string  $error  The error message
     * @return bool True if the notification was marked as failed
     */
    public function markAsFailed(string $id, string $error): bool;

    /**
     * Mark all notifications in a session as read.
     *
     * @param  string  $sessionId  The session ID
     * @return int The number of notifications marked as read
     */
    public function markAsReadBySession(string $sessionId): int;

    /**
     * Count notifications for a notifiable entity.
     *
     * @param  Model  $notifiable  The notifiable entity
     * @return int The number of notifications
     */
    public function countByNotifiable(Model $notifiable): int;

    /**
     * Count notifications by status for a notifiable entity.
     *
     * @param  Model  $notifiable  The notifiable entity
     * @param  NotificationStatus  $status  The notification status
     * @return int The number of notifications with the given status
     */
    public function countByStatus(Model $notifiable, NotificationStatus $status): int;

    /**
     * Count notifications by session ID.
     *
     * @param  string  $sessionId  The session ID
     * @return int The number of notifications in the session
     */
    public function countBySession(string $sessionId): int;

    /**
     * Find notifications by session ID.
     *
     * @param  string  $sessionId  The session ID
     * @return Builder The query builder instance
     */
    public function findBySession(string $sessionId): Builder;
}
