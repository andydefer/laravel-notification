<?php

// src/Enums/ErrorCode.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Enums;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use AndyDefer\PhpVo\Enums\HttpStatusCode;

enum ErrorCode: string
{
    case DEVICE_NOT_FOUND = 'DEVICE_NOT_FOUND';
    case NOTIFIABLE_MISMATCH = 'NOTIFIABLE_MISMATCH';
    case PING_FAILED = 'PING_FAILED';

    public function getHttpStatusCode(): HttpStatusCode
    {
        return match ($this) {
            self::DEVICE_NOT_FOUND => HttpStatusCode::NOT_FOUND,
            self::NOTIFIABLE_MISMATCH => HttpStatusCode::FORBIDDEN,
            self::PING_FAILED => HttpStatusCode::INTERNAL_SERVER_ERROR,
        };
    }

    public function getMessage(): string
    {
        return match ($this) {
            self::DEVICE_NOT_FOUND => 'Device not found',
            self::NOTIFIABLE_MISMATCH => 'Device does not belong to the authenticated notifiable',
            self::PING_FAILED => 'Ping failed',
        };
    }

    public function toResponseData(
        ?string $message = null,
        array|StrictAssociative|StrictDataObject|null $errors = null,
    ): ErrorResponseData {
        return ErrorResponseData::from([
            'errorCode' => $this->value,
            'message' => $message ?? $this->getMessage(),
            'status' => $this->getHttpStatusCode(),
            'errors' => $errors,
        ]);
    }

    public function toJsonResponseFactory(
        ?string $message = null,
        array|StrictAssociative|StrictDataObject|null $errors = null,
    ): ResponseFactory {
        return ResponseFactory::json(
            $this->toResponseData($message, $errors),
            $this->getHttpStatusCode(),
        );
    }
}
