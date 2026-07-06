<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;

final class ErrorMessageVO extends AbstractValueObject
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            $trimmed = 'An error occurred';
        }

        if (strlen($trimmed) > 1000) {
            $trimmed = substr($trimmed, 0, 997).'...';
        }

        $this->value = $trimmed;
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
