<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\LaravelNotification\Contracts\ChannelInterface;

final class FqcnChannelVO extends AbstractValueObject
{
    public readonly string $value;

    public function __construct(string $channelClass)
    {
        self::validate($channelClass);
        $this->value = $channelClass;
    }

    public static function validate(string $channelClass): void
    {
        if (! class_exists($channelClass)) {
            throw new \InvalidArgumentException(
                sprintf('Channel class "%s" does not exist.', $channelClass)
            );
        }

        if (! is_subclass_of($channelClass, ChannelInterface::class)) {
            throw new \InvalidArgumentException(
                sprintf('Class "%s" must implement %s', $channelClass, ChannelInterface::class)
            );
        }

        $reflection = new \ReflectionClass($channelClass);
        if ($reflection->isAbstract()) {
            throw new \InvalidArgumentException(
                sprintf('Channel class "%s" cannot be abstract.', $channelClass)
            );
        }

        if ($reflection->isInterface()) {
            throw new \InvalidArgumentException(
                sprintf('Channel class "%s" cannot be an interface.', $channelClass)
            );
        }

        if (! $reflection->isInstantiable()) {
            throw new \InvalidArgumentException(
                sprintf('Channel class "%s" is not instantiable.', $channelClass)
            );
        }
    }

    public function validateDestination(string $destination): void
    {
        $this->value::validateDestination($destination);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
