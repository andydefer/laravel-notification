<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class FirebaseConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly ?string $credentials_path = null,
        public readonly ?string $project_id = null,
        public readonly string $scope = 'https://www.googleapis.com/auth/firebase.messaging',
        public readonly int $timeout = 30,

        // Client-side Firebase config (public, exposed to Vite)
        public readonly ?string $api_key = null,
        public readonly ?string $auth_domain = null,
        public readonly ?string $storage_bucket = null,
        public readonly ?string $messaging_sender_id = null,
        public readonly ?string $app_id = null,
        public readonly ?string $measurement_id = null,
        public readonly ?string $vapid_key = null,
        public readonly ?string $legacy_key = null,
    ) {}
}
