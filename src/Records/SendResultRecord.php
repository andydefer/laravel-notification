<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\ValueObjects\ErrorMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;

final class SendResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly FqcnChannelVO $channel,
        public readonly string $destination,
        public readonly bool $success,
        public readonly ?ErrorMessageVO $error_message = null,
    ) {}
}
