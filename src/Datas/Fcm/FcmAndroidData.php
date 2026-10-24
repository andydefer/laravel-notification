<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Datas\Fcm;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Traits\Hydratable;

/**
 * Bloc `message.android` de FCM HTTP v1.
 *
 * Conteneur du sous-bloc `notification` Android. Nullable : si null,
 * le driver n'envoie PAS de bloc `android` à FCM.
 */
final class FcmAndroidData extends AbstractData
{
    use Hydratable;

    public function __construct(
        public readonly ?FcmAndroidNotificationData $notification = null,
    ) {}
}
