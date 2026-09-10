<?php

// src/Traits/HasNotifications.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Traits;

use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Contracts\Repositories\NotificationRepositoryInterface;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\Records\NotificationFilterRecord;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Trait for models that can receive notifications.
 *
 * Provides utility methods to interact with notifications.
 * Wraps the NotificationRepository to avoid duplicating logic.
 *
 * @phpstan-require-extends Model
 *
 * @property-read Collection<int, Notification> $notifications
 * @property-read int $unread_notifications_count
 * @property-read Collection<int, Notification> $unread_notifications
 * @property-read Collection<int, Notification> $read_notifications
 * @property-read Collection<int, Notification> $sent_notifications
 * @property-read Collection<int, Notification> $pending_notifications
 * @property-read Collection<int, Notification> $failed_notifications
 * @property-read Collection<int, Notification> $latest_notifications
 * @property-read Collection<int, Notification> $database_notifications
 * @property-read Collection<int, Notification> $latest_database_notifications
 * @property-read bool $has_unread_notifications
 * @property-read bool $has_notifications
 */
trait HasNotifications
{
    /**
     * Get all notifications for the model.
     */
    public function notifications(): MorphMany
    {
        /** @var Model $this */
        return $this->morphMany(Notification::class, 'notifiable');
    }

    /**
     * Get the unread notifications count.
     *
     * @return Attribute<int, never>
     */
    protected function unreadNotificationsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->notificationRepository()->count(
                $this->notificationFilter(['read' => false])
            ),
        );
    }

    /**
     * Get all unread notifications.
     *
     * @return Attribute<Collection<int, Notification>, never>
     */
    protected function unreadNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->notificationRepository()->findBy(
                new FindByRecord(filters: $this->notificationFilter(['read' => false]))
            ),
        );
    }

    /**
     * Get all read notifications.
     *
     * @return Attribute<Collection<int, Notification>, never>
     */
    protected function readNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->notificationRepository()->findBy(
                new FindByRecord(filters: $this->notificationFilter(['read' => true]))
            ),
        );
    }

    /**
     * Get all sent notifications.
     *
     * @return Attribute<Collection<int, Notification>, never>
     */
    protected function sentNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->notificationRepository()->findBy(
                new FindByRecord(filters: $this->notificationFilter([
                    'status' => NotificationStatus::SENT,
                ]))
            ),
        );
    }

    /**
     * Get all pending notifications.
     *
     * @return Attribute<Collection<int, Notification>, never>
     */
    protected function pendingNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->notificationRepository()->findBy(
                new FindByRecord(filters: $this->notificationFilter([
                    'status' => NotificationStatus::PENDING,
                ]))
            ),
        );
    }

    /**
     * Get all failed notifications.
     *
     * @return Attribute<Collection<int, Notification>, never>
     */
    protected function failedNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->notificationRepository()->findBy(
                new FindByRecord(filters: $this->notificationFilter([
                    'status' => NotificationStatus::FAILED,
                ]))
            ),
        );
    }

    /**
     * Get the latest notifications.
     *
     * @return Attribute<Collection<int, Notification>, never>
     */
    protected function latestNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->notificationRepository()->findBy(
                new FindByRecord(
                    filters: $this->notificationFilter(),
                    sortBy: new SortColumns('created_at:desc'),
                    limit: 10,
                )
            ),
        );
    }

    /**
     * Get the latest database notifications.
     *
     * Returns the last 10 notifications sent through the DatabaseChannel.
     *
     * @return Attribute<Collection<int, Notification>, never>
     */
    protected function latestDatabaseNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->notificationRepository()->findBy(
                new FindByRecord(
                    filters: $this->notificationFilter([
                        'channel' => DatabaseChannel::class,
                    ]),
                    sortBy: new SortColumns('created_at:desc'),
                    limit: 10,
                )
            ),
        );
    }

    /**
     * Get all database notifications.
     *
     * Returns all notifications sent through the DatabaseChannel.
     *
     * @return Attribute<Collection<int, Notification>, never>
     */
    protected function databaseNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->notificationRepository()->findBy(
                new FindByRecord(
                    filters: $this->notificationFilter([
                        'channel' => DatabaseChannel::class,
                    ]),
                    sortBy: new SortColumns('created_at:desc'),
                )
            ),
        );
    }

    /**
     * Check if the model has any unread notifications.
     *
     * @return Attribute<bool, never>
     */
    protected function hasUnreadNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->notificationRepository()->exists(
                $this->notificationFilter(['read' => false])
            ),
        );
    }

    /**
     * Check if the model has any notifications.
     *
     * @return Attribute<bool, never>
     */
    protected function hasNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->notificationRepository()->exists(
                $this->notificationFilter()
            ),
        );
    }

    /**
     * Mark a specific notification as read.
     */
    public function markNotificationAsRead(string $notificationId): bool
    {
        return $this->notificationRepository()->markAsRead($notificationId);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllNotificationsAsRead(): int
    {
        $notifications = $this->notificationRepository()->findBy(
            new FindByRecord(filters: $this->notificationFilter(['read' => false]))
        );

        $count = 0;

        foreach ($notifications as $notification) {
            if ($this->notificationRepository()->markAsRead($notification->id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete a specific notification.
     */
    public function deleteNotification(string $notificationId): bool
    {
        return $this->notificationRepository()->delete($notificationId);
    }

    /**
     * Delete all notifications.
     */
    public function deleteAllNotifications(): int
    {
        return $this->notificationRepository()->deleteBulk($this->notificationFilter());
    }

    /**
     * Delete all read notifications.
     */
    public function deleteReadNotifications(): int
    {
        return $this->notificationRepository()->deleteBulk(
            $this->notificationFilter(['read' => true])
        );
    }

    /**
     * Count notifications by status.
     */
    public function countNotificationsByStatus(NotificationStatus $status): int
    {
        return $this->notificationRepository()->count(
            $this->notificationFilter(['status' => $status])
        );
    }

    /**
     * Get notifications for a specific channel.
     *
     * @param  string  $channel  The channel class name or identifier
     * @param  int  $limit  Maximum number of notifications to retrieve
     * @return Collection<int, Notification>
     */
    public function notificationsByChannel(string $channel, int $limit = 10): Collection
    {
        return $this->notificationRepository()->findBy(
            new FindByRecord(
                filters: $this->notificationFilter(['channel' => $channel]),
                sortBy: new SortColumns('created_at:desc'),
                limit: $limit,
            )
        );
    }

    /**
     * Get the notification repository instance.
     */
    protected function notificationRepository(): NotificationRepositoryInterface
    {
        return app(NotificationRepositoryInterface::class);
    }

    /**
     * Build a filter record scoped to this model.
     */
    protected function notificationFilter(array $overrides = []): NotificationFilterRecord
    {
        /** @var Model $this */

        return NotificationFilterRecord::from(array_merge([
            'notifiable_type' => $this->getMorphClass(),
            'notifiable_id' => $this->getKey(),
        ], $overrides));
    }
}
