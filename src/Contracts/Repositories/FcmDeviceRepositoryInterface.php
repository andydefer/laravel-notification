<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts\Repositories;

use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Records\FcmDeviceRecord;
use AndyDefer\Repository\AbstractRepositoryInterface;

/**
 * Contract for the FCM device repository.
 *
 * @extends AbstractRepositoryInterface<FcmDevice, FcmDeviceRecord>
 */
interface FcmDeviceRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Create or update an FCM device from a record.
     *
     * Uses (notifiable_type, notifiable_id, device_id) as the natural key.
     * If a matching device exists, its token and metadata are refreshed.
     * Otherwise, a new device row is created.
     *
     * @param  FcmDeviceRecord  $record  The device data
     * @return FcmDevice The persisted device
     */
    public function upsertFor(FcmDeviceRecord $record): FcmDevice;
}
