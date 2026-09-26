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
    ) {}
}
