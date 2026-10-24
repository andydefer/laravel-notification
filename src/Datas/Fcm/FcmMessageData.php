<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Datas\Fcm;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Traits\Hydratable;

/**
 * Enveloppe FCM HTTP v1 complète.
 *
 * Représente le contrat du payload envoyé à FCM. Aucune logique
 * de sérialisation ici — c'est le rôle du driver.
 *
 * Le consommateur (Kotlin, TypeScript, Go…) peut créer une classe
 * miroir pour typer la réception.
 */
final class FcmMessageData extends AbstractData
{
    use Hydratable;

    public function __construct(
        /** Token du device destinataire. */
        public readonly ?string $token = null,

        /** Bloc générique — titre + corps. */
        public readonly ?FcmNotificationData $notification = null,

        /** Bloc Android — nullable, omis si absent. */
        public readonly ?FcmAndroidData $android = null,

        /** Bloc data — type + payload métier. */
        public readonly ?FcmDataPayloadData $data = null,
    ) {}
}
