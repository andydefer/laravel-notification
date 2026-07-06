<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\Records\NotificationFilterRecord;
use AndyDefer\LaravelNotification\Records\NotificationRecord;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class NotificationRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct(
            modelClass: Notification::class,
            recordClass: NotificationRecord::class,
        );
    }

    public function markAsRead(string $id): bool
    {
        $model = $this->find($id);
        if ($model === null) {
            return false;
        }

        return $model->update(['read_at' => now()]);
    }

    public function markAsDelivered(string $id): bool
    {
        $model = $this->find($id);
        if ($model === null) {
            return false;
        }

        return $model->update(['status' => NotificationStatus::DELIVERED->value]);
    }

    public function markAsSent(string $id): bool
    {
        $model = $this->find($id);
        if ($model === null) {
            return false;
        }

        return $model->update([
            'status' => NotificationStatus::SENT->value,
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $id, string $error): bool
    {
        $model = $this->find($id);
        if ($model === null) {
            return false;
        }

        return $model->update([
            'status' => NotificationStatus::FAILED->value,
            'error' => $error,
        ]);
    }

    public function markAsReadBySession(string $sessionId): int
    {
        return $this->modelClass::where('session_id', $sessionId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function countByNotifiable(Model $notifiable): int
    {
        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
        ]);

        return $this->count($filter);
    }

    public function countByStatus(Model $notifiable, NotificationStatus $status): int
    {
        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'status' => $status,
        ]);

        return $this->count($filter);
    }

    public function countBySession(string $sessionId): int
    {
        $filter = NotificationFilterRecord::from([
            'session_id' => $sessionId,
        ]);

        return $this->count($filter);
    }

    public function findBySession(string $sessionId): Builder
    {
        return $this->modelClass::where('session_id', $sessionId);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof NotificationFilterRecord) {
            return;
        }

        if ($filters->session_id !== null) {
            $query->where('session_id', $filters->session_id->getValue());
        }

        if ($filters->channel !== null) {
            $query->where('channel', $filters->channel->getValue());
        }

        if ($filters->destination !== null) { // ✅ NOUVEAU
            $query->where('destination', $filters->destination);
        }

        if ($filters->notifiable_type !== null) {
            $query->where('notifiable_type', $filters->notifiable_type);
        }

        if ($filters->notifiable_id !== null) {
            $query->where('notifiable_id', $filters->notifiable_id);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->read !== null) {
            if ($filters->read) {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }
    }
}
