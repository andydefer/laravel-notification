<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts;

use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;

/**
 * Interface for notifiable entities.
 *
 * Defines the contract for models that can receive notifications.
 * Implementing classes must provide notification channels and identification.
 *
 * @example
 * class User extends Model implements NotifiableInterface
 * {
 *     public function getNotificationChannels(): NotificationRouteCollection
 *     {
 *         return NotificationRouteCollection::from([
 *             new NotificationRouteVO('mail', 'user@example.com'),
 *             new NotificationRouteVO('sms', '+1234567890'),
 *         ]);
 *     }
 * }
 */
interface NotifiableInterface
{
    /**
     * Get the notification channels and routes for this entity.
     *
     * Returns a collection of notification routes, each containing
     * the channel type and destination address.
     *
     * @return NotificationRouteCollection Collection of notification routes
     */
    public function getNotificationChannels(): NotificationRouteCollection;

    /**
     * Get the morph class name for polymorphic relationships.
     *
     * @return string The morph class name (e.g., 'App\Models\User')
     */
    public function getMorphClass();

    /**
     * Get the primary key value of the entity.
     *
     * @return int|string The primary key value
     */
    public function getKey();
}
