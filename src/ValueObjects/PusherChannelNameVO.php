<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * Value Object representing a private Pusher channel name.
 *
 * Encapsulates the naming rules for private channels and their
 * device sub-channels. Ensures consistency between producers
 * (models declaring routes) and consumers (PusherAuthAction).
 *
 * The prefix is resolved internally from the notification config
 * (`notification.channels.pusher.user_channel_prefix`).
 */
final class PusherChannelNameVO extends AbstractValueObject
{
    private readonly string $prefix;

    public function __construct(private readonly string $value)
    {
        $this->prefix = self::resolvePrefix();

        if ($value === '' || ! str_starts_with($value, $this->prefix)) {
            throw new \InvalidArgumentException(
                sprintf('Pusher channel name must start with "%s".', $this->prefix),
            );
        }
    }

    /**
     * Create a channel name from a model.
     *
     * Uses the model morph class and key to build a stable,
     * sanitized channel name.
     */
    public static function forModel(Model $model): self
    {
        $morphType = $model->getMorphClass();
        $key = $model->getKey();

        if ($key === null || $morphType === '') {
            throw new \InvalidArgumentException('Cannot build a channel name from an anonymous model.');
        }

        return new self(sprintf(
            '%s%s-%s',
            self::resolvePrefix(),
            self::sanitize($morphType),
            self::sanitize((string) $key),
        ));
    }

    /**
     * Create a device sub-channel name from a model and a device id.
     */
    public static function forDevice(Model $model, string $deviceId): self
    {
        if ($deviceId === '') {
            throw new \InvalidArgumentException('Device id cannot be empty.');
        }

        return new self(sprintf(
            '%s-device-%s',
            self::forModel($model)->getValue(),
            self::sanitize($deviceId),
        ));
    }

    /**
     * Determine whether this channel is allowed for the given model.
     *
     * A channel is allowed if it equals the model's channel or starts
     * with the model's device prefix.
     */
    public function belongsTo(Model $model): bool
    {
        $base = self::forModel($model)->getValue();

        if ($this->value === $base) {
            return true;
        }

        return str_starts_with($this->value, $base.'-device-');
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function resolvePrefix(): string
    {
        return app(NotificationConfig::class)
            ->getPusherConfig()
            ->user_channel_prefix;
    }

    private static function sanitize(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-=@,.;]/', '_', $value) ?? '';
    }
}
