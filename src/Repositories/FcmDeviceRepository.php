<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Contracts\Repositories\FcmDeviceRepositoryInterface;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Records\FcmDeviceFilterRecord;
use AndyDefer\LaravelNotification\Records\FcmDeviceRecord;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

final class FcmDeviceRepository extends AbstractRepository implements FcmDeviceRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(FcmDevice::class, FcmDeviceRecord::class);
    }

    /**
     * {@inheritDoc}
     */
    public function upsertFor(FcmDeviceRecord $record): FcmDevice
    {
        // 1. Priorité : une ligne existe déjà avec ce token → on la remplace.
        $existingByToken = $this->model->newQuery()
            ->where('token', $record->token)
            ->first();

        if ($existingByToken !== null) {
            $existingByToken->fill([
                'notifiable_type' => $record->notifiable_type,
                'notifiable_id' => $record->notifiable_id,
                'device_id' => $record->device_id,
                'platform' => $record->platform?->value,
                'user_agent' => $record->user_agent,
                'last_seen_at' => $record->last_seen_at ?? now(),
            ]);
            $existingByToken->save();

            return $existingByToken;
        }

        // 2. Sinon : recherche par (notifiable_type, notifiable_id, device_id).
        $key = [
            'notifiable_type' => $record->notifiable_type,
            'notifiable_id' => $record->notifiable_id,
            'device_id' => $record->device_id,
        ];

        $values = [
            'token' => $record->token,
            'platform' => $record->platform?->value,
            'user_agent' => $record->user_agent,
            'last_seen_at' => $record->last_seen_at ?? now(),
        ];

        $existing = $this->model->newQuery()->where($key)->first();

        if ($existing !== null) {
            $existing->fill($values);
            $existing->save();

            return $existing;
        }

        // 3. Aucune ligne trouvée : création.
        return $this->model->newQuery()->create($key + $values);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof FcmDeviceFilterRecord) {
            return;
        }

        if ($filters->device_id !== null) {
            $query->where('device_id', $filters->device_id);
        }

        if ($filters->token !== null) {
            $query->where('token', $filters->token);
        }

        if ($filters->platform !== null) {
            $query->where('platform', $filters->platform->value);
        }

        if ($filters->notifiable_type !== null) {
            $query->where('notifiable_type', $filters->notifiable_type);
        }

        if ($filters->notifiable_id !== null) {
            $query->where('notifiable_id', $filters->notifiable_id);
        }

        if ($filters->from_last_seen_at !== null) {
            $query->where('last_seen_at', '>=', $filters->from_last_seen_at);
        }

        if ($filters->to_last_seen_at !== null) {
            $query->where('last_seen_at', '<=', $filters->to_last_seen_at);
        }
    }
}
