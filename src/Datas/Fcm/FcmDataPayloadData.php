<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Datas\Fcm;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Traits\Hydratable;

/**
 * Bloc `message.data` de FCM HTTP v1.
 *
 * ⚠️ `$payload` est une string JSON encodée (format FCM : Map<String,String>).
 * Le décodage se fait côté consommateur (Kotlin/TypeScript/Go).
 */
final class FcmDataPayloadData extends AbstractData
{
    use Hydratable;

    public function __construct(
        /** Discriminant métier, ex : "profile_updated". */
        public readonly ?string $type = null,

        /** Payload métier encodé en JSON string, ex : '{"user_id":"61"}'. */
        public readonly ?string $payload = null,
    ) {}
}
