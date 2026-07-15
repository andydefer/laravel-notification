<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;

final class NotificationTaskPayloadRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $notifiable_type,
        public readonly int $notifiable_id,
        public readonly MessageBodyVO $body,
        public readonly MessageSubjectVO $subject,
        public readonly string $type,
        public readonly StrictDataObject $data,
        public readonly FqcnChannelCollection $channels = new FqcnChannelCollection,
        public readonly ?int $limit_per_channel = null,
        public readonly ?StrictAssociative $destination_filter = null,
    ) {}
}
