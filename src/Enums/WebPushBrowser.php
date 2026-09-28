<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Enums;

enum WebPushBrowser: string
{
    case CHROME = 'chrome';
    case FIREFOX = 'firefox';
    case SAFARI = 'safari';
    case EDGE = 'edge';
    case OTHER = 'other';
}
