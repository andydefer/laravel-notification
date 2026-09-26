<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Enums;

enum FcmPlatform: string
{
    case WEB = 'web';
    case IOS = 'ios';
    case ANDROID = 'android';
}
