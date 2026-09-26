<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Traits;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trait providing FCM device relationships to notifiable models.
 *
 * Adds a polymorphic relation to FcmDevice so any model (User, Admin,
 * Shop, CheckPoint, etc.) can own multiple FCM devices.
 */
trait HasFcmDevices
{
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
}
