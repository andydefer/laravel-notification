<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Datas\Fcm;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Traits\Hydratable;

/**
 * Bloc `message.notification` de FCM HTTP v1.
 *
 * Champs génériques acceptés par Firebase (title, body).
 * Les champs spécifiques Android vont dans {@see FcmAndroidNotificationData}.
 *
 * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#Notification
 */
final class FcmNotificationData extends AbstractData
{
    use Hydratable;

    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $body = null,
    ) {}
}
