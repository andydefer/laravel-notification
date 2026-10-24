<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Datas\Fcm;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Traits\Hydratable;

/**
 * Bloc `message.android.notification` de FCM HTTP v1.
 *
 * Champs visuels spécifiques Android. Tous nullables : si un champ
 * est null, le driver l'omet de l'envoi et Android utilise ses défauts.
 *
 * @see https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#AndroidNotification
 */
final class FcmAndroidNotificationData extends AbstractData
{
    use Hydratable;

    public function __construct(
        /** Nom de drawable local Android, ex : "ic_notification". */
        public readonly ?string $icon = null,

        /** URL HTTPS publique de l'image grande (BigPictureStyle). */
        public readonly ?string $image = null,

        /** Nom du canal Android, ex : "afya_payments". */
        public readonly ?string $channelId = null,

        /** Son, ex : "default". */
        public readonly ?string $sound = null,

        /** Tag de regroupement. */
        public readonly ?string $tag = null,

        /** Action au clic, ex : "OPEN_PROFILE". */
        public readonly ?string $clickAction = null,
    ) {}
}
