<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Configuration du driver APNS (Apple Push Notification service).
 *
 * Toutes les propriétés correspondent aux credentials Apple Developer :
 *   - `key_path`   → chemin vers la clé .p8
 *   - `key_id`     → Key ID associé à la clé .p8
 *   - `team_id`    → Team ID du compte Apple Developer
 *   - `bundle_id`  → Bundle ID de l'application iOS
 *   - `production` → true pour l'endpoint production, false pour sandbox
 */
final class ApnsConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly ?string $key_path = null,
        public readonly ?string $key_id = null,
        public readonly ?string $team_id = null,
        public readonly ?string $bundle_id = null,
        public readonly bool $production = true,
        public readonly ?string $default_sound = 'default',
        public readonly int $timeout = 30,
    ) {}
}
