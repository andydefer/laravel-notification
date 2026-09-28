<?php

// src/Traits/HasNotifications.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Traits;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Contracts\Repositories\NotificationRepositoryInterface;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
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
 * Provides utility methods to interact with notifications, FCM devices,
 * and Web Push subscriptions. Wraps the NotificationRepository to avoid
 * duplicating logic.
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
 * @property-read int $unread_database_notifications_count
 * @property-read Collection<int, Notification> $unread_database_notifications
 * @property-read bool $has_unread_database_notifications
 * @property-read bool $has_unread_notifications
 * @property-read bool $has_notifications
 * @property-read Collection<int, FcmDevice> $fcm_devices
 * @property-read StringTypedCollection $fcm_tokens
 * @property-read bool $has_fcm_devices
 * @property-read Collection<int, WebPushSubscription> $web_push_subscriptions
 * @property-read StringTypedCollection $web_push_endpoints
 * @property-read bool $has_web_push_subscriptions
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
     * Get all FCM devices owned by this model.
     *
     * @return MorphMany<FcmDevice>
     */
    public function fcmDevices(): MorphMany
    {
        /** @var Model $this */
        return $this->morphMany(FcmDevice::class, 'notifiable');
    }

    /**
     * Get all Web Push subscriptions owned by this model.
     *
     * @return MorphMany<WebPushSubscription>
     */
    public function webPushSubscriptions(): MorphMany
    {
        /** @var Model $this */
        return $this->morphMany(WebPushSubscription::class, 'notifiable');
    }

    /**
     * Get all FCM registration tokens owned by this model.
     *
     * @return Attribute<StringTypedCollection, never>
     */
    protected function fcmTokens(): Attribute
    {
        return Attribute::get(
            fn (): StringTypedCollection => StringTypedCollection::from(
                $this->fcmDevices()->pluck('token')->all(),
            ),
        );
    }

    /**
     * Determine if this model has at least one FCM device registered.
     *
     * @return Attribute<bool, never>
     */
    protected function hasFcmDevices(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->fcmDevices()->exists(),
        );
    }

    /**
     * Get all Web Push endpoints owned by this model.
     *
     * @return Attribute<StringTypedCollection, never>
     */
    protected function webPushEndpoints(): Attribute
    {
        return Attribute::get(
            fn (): StringTypedCollection => StringTypedCollection::from(
                $this->webPushSubscriptions()->pluck('endpoint')->all(),
            ),
        );
    }

    /**
     * Determine if this model has at least one Web Push subscription.
     *
     * @return Attribute<bool, never>
     */
    protected function hasWebPushSubscriptions(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->webPushSubscriptions()->exists(),
        );
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
     * Get the unread database notifications count.
     *
     * @return Attribute<int, never>
     */
    protected function unreadDatabaseNotificationsCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->notificationRepository()->count(
                $this->notificationFilter([
                    'channel' => DatabaseChannel::class,
                    'read' => false,
                ])
            ),
        );
    }

    /**
     * Get all unread database notifications.
     *
     * Returns all notifications sent through the DatabaseChannel that are unread.
     *
     * @return Attribute<Collection<int, Notification>, never>
     */
    protected function unreadDatabaseNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): Collection => $this->notificationRepository()->findBy(
                new FindByRecord(
                    filters: $this->notificationFilter([
                        'channel' => DatabaseChannel::class,
                        'read' => false,
                    ]),
                    sortBy: new SortColumns('created_at:desc'),
                )
            ),
        );
    }

    /**
     * Check if the model has any unread database notifications.
     *
     * @return Attribute<bool, never>
     */
    protected function hasUnreadDatabaseNotifications(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->notificationRepository()->exists(
                $this->notificationFilter([
                    'channel' => DatabaseChannel::class,
                    'read' => false,
                ])
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
