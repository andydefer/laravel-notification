<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class NotificationStatsRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $total = 0,
        public readonly int $sent = 0,
        public readonly int $failed = 0,
        public readonly int $delivered = 0,
        public readonly int $pending = 0,
        public readonly float $success_rate = 0.0,
    ) {}

    public function getSuccessRate(): float
    {
        return $this->success_rate;
    }

    public function getPercentageSent(): float
    {
        return $this->total > 0 ? round(($this->sent / $this->total) * 100, 2) : 0;
    }

    public function getPercentageFailed(): float
    {
        return $this->total > 0 ? round(($this->failed / $this->total) * 100, 2) : 0;
    }

    public function getPercentageDelivered(): float
    {
        return $this->total > 0 ? round(($this->delivered / $this->total) * 100, 2) : 0;
    }

    public function getPercentagePending(): float
    {
        return $this->total > 0 ? round(($this->pending / $this->total) * 100, 2) : 0;
    }

    public function isSuccess(): bool
    {
        return $this->failed === 0 && $this->sent > 0;
    }

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }

    public function hasDeliveries(): bool
    {
        return $this->delivered > 0;
    }

    public function hasPending(): bool
    {
        return $this->pending > 0;
    }
}
