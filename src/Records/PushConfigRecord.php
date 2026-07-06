<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

final class PushConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $platform = 'fcm',
        public readonly ?string $fcm_api_key = null,
        public readonly ?string $fcm_project_id = null,
        public readonly ?string $apns_key_path = null,
        public readonly ?string $apns_key_id = null,
        public readonly ?string $apns_team_id = null,
        public readonly ?string $apns_bundle_id = null,
        public readonly ?string $default_sound = 'default',
        public readonly ?StrictDataObject $default_tokens = null,
    ) {}
}
