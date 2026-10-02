<?php

// src/Collections/WebPushSubscriptionDataCollection.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelNotification\Datas\WebPushSubscriptionData;

/**
 * @extends AbstractTypedCollection<WebPushSubscriptionData>
 */
final class WebPushSubscriptionDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(WebPushSubscriptionData::class);
    }
}
