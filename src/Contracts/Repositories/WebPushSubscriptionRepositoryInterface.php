<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts\Repositories;

use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\LaravelNotification\Records\WebPushSubscriptionRecord;
use AndyDefer\Repository\AbstractRepositoryInterface;

/**
 * Contract for the Web Push subscription repository.
 *
 * Exposes persistence operations dedicated to {@see WebPushSubscription} records
 * on top of the generic repository API inherited from {@see AbstractRepositoryInterface}.
 */
interface WebPushSubscriptionRepositoryInterface extends AbstractRepositoryInterface
{
    /**
     * Persist a subscription identified by its unique endpoint.
     *
     * An existing row with the same endpoint is updated in place, so the
     * operation is idempotent: calling it repeatedly with the same endpoint
     * never produces duplicates.
     *
     * @param  WebPushSubscriptionRecord  $record  The subscription data to persist
     * @return WebPushSubscription The persisted subscription instance
     */
    public function upsertFor(WebPushSubscriptionRecord $record): WebPushSubscription;
}
