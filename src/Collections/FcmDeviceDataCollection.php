<?php

// src/Collections/FcmDeviceDataCollection.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelNotification\Datas\FcmDeviceData;

/**
 * @extends AbstractTypedCollection<FcmDeviceData>
 */
final class FcmDeviceDataCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(FcmDeviceData::class);
    }
}
