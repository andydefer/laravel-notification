<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tasks;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Helpers\WebPushPingPong;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;

final class PingAndPruneWebPushSubscriptionTask extends AbstractUniqueTask
{
    protected function before(StrictDataObject $payload): void
    {
        if (! $payload->has('subscription_id')) {
            throw new \InvalidArgumentException('subscription_id is required.');
        }

        if ((string) $payload->subscription_id === '') {
            throw new \InvalidArgumentException('subscription_id cannot be empty.');
        }
    }

    protected function process(): void
    {
        $subscriptionId = (string) $this->context->getPayload()->subscription_id;
        $subscription = WebPushSubscription::find($subscriptionId);

        if ($subscription === null) {
            throw new \RuntimeException(sprintf('Web Push subscription not found: %s', $subscriptionId));
        }

        /** @var WebPushPingPong $pingPong */
        $pingPong = $this->context->getLaravelApp()->make(WebPushPingPong::class);

        $this->info(new DescriptionVO(sprintf(
            'Pinging Web Push subscription %s (endpoint ...%s)',
            $subscription->id,
            substr((string) $subscription->endpoint, -12),
        )));

        $status = $pingPong->pingOrPrune($subscription);

        $this->info(new DescriptionVO(sprintf(
            'Subscription %s -> %s',
            $subscription->id,
            $status->value,
        )));

        if ($status !== PingStatus::PONG) {
            $this->info(new DescriptionVO(sprintf(
                'Subscription %s is not reachable (%s).',
                $subscription->id,
                $status->value,
            )));
        }
    }

    protected function after(bool $success, ?DescriptionVO $error = null): void
    {
        if ($success) {
            return;
        }

        $this->error(new DescriptionVO(sprintf(
            'Ping/prune failed: %s',
            $error?->getValue() ?? 'unknown error',
        )));
    }
}
