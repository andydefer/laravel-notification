<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Value Object representing a datetime for notification scheduling.
 *
 * Provides comparison methods for scheduling notifications.
 */
final class NotificationDateTimeVO extends AbstractValueObject
{
    private const FORMAT = 'Y-m-d\TH:i:sP';

    private readonly string $value;

    /**
     * Constructor for the notification datetime.
     *
     * @param  string|null  $value  The datetime string in ISO 8601 format
     *
     * @throws InvalidArgumentException If the datetime format is invalid
     */
    public function __construct(?string $value = null)
    {
        $value = $value ?? Carbon::now()->format(self::FORMAT);

        $date = Carbon::createFromFormat(self::FORMAT, $value);

        if (! $date || $date->format(self::FORMAT) !== $value) {
            throw new InvalidArgumentException("Invalid ISO 8601 datetime: {$value}");
        }

        $this->value = $value;
    }

    /**
     * Get the raw value.
     *
     * @return string The datetime in ISO 8601 format
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Convert to Carbon instance.
     *
     * @return Carbon The Carbon instance
     */
    public function toCarbon(): Carbon
    {
        return Carbon::createFromFormat(self::FORMAT, $this->value);
    }

    /**
     * Check if this datetime is strictly after another.
     *
     * @param  self  $other  The other datetime
     * @return bool True if this datetime is after the other
     */
    public function isAfter(self $other): bool
    {
        return $this->toCarbon()->gt($other->toCarbon());
    }

    /**
     * Check if this datetime is strictly before another.
     *
     * @param  self  $other  The other datetime
     * @return bool True if this datetime is before the other
     */
    public function isBefore(self $other): bool
    {
        return $this->toCarbon()->lt($other->toCarbon());
    }

    /**
     * Check if this datetime is before or equal to another.
     *
     * @param  self  $other  The other datetime
     * @return bool True if this datetime is before or equal to the other
     */
    public function isBeforeOrEqual(self $other): bool
    {
        return $this->toCarbon()->lte($other->toCarbon());
    }

    /**
     * Check if this datetime is after or equal to another.
     *
     * @param  self  $other  The other datetime
     * @return bool True if this datetime is after or equal to the other
     */
    public function isAfterOrEqual(self $other): bool
    {
        return $this->toCarbon()->gte($other->toCarbon());
    }

    /**
     * Check if this datetime is equal to another.
     *
     * @param  self  $other  The other datetime
     * @return bool True if the datetimes are equal
     */
    public function equals(AbstractValueObject $other): bool
    {
        return $this->toCarbon()->eq($other->toCarbon());
    }

    /**
     * Format the datetime for database storage.
     *
     * @return string The datetime in database format (Y-m-d H:i:s)
     */
    public function forDatabase(): string
    {
        return $this->toCarbon()->format('Y-m-d H:i:s');
    }

    /**
     * Format the datetime for display.
     *
     * @return string The datetime in display format (d/m/Y H:i:s)
     */
    public function forDisplay(): string
    {
        return $this->toCarbon()->format('d/m/Y H:i:s');
    }

    /**
     * Convert to string.
     *
     * @return string The datetime in ISO 8601 format
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
