<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Helpers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\WebPushChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Contracts\PingPongInterface;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Ping/pong helper for Web Push subscriptions.
 *
 * Sends a lightweight notification to a subscription's endpoint to
 * verify it is still active. Any failure is treated as invalid: the
 * subscription is pruned.
 */
final class WebPushPingPong implements PingPongInterface
{
    public const PING_TYPE = 'ping';

    public const PING_SUBJECT = 'ping';

    public const PING_BODY = 'ping';

    public function __construct(
        private readonly NotificationServiceInterface $service,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function ping(Model $notifiable): PingStatus
    {
        if (! $notifiable instanceof NotifiableInterface) {
            throw new RuntimeException('Subscription must implement NotifiableInterface.');
        }

        $message = new NotificationMessageVO(
            body: new MessageBodyVO(self::PING_BODY),
            subject: new MessageSubjectVO(self::PING_SUBJECT),
            type: self::PING_TYPE,
            data: new StrictDataObject([
                'subscription_id' => (string) $notifiable->getKey(),
            ]),
        );

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(WebPushChannel::class));

        $record = SendNowRecord::from([
            'channels' => $channels,
            'limit_per_channel' => 1,
        ]);

        $result = $this->service->sendNow($notifiable, $message, $record);

        if (! $result->allSuccess()) {
            return PingStatus::INVALID;
        }

        $notifiable->setAttribute('last_seen_at', now());
        $notifiable->save();

        return PingStatus::PONG;
    }

    /**
     * {@inheritDoc}
     */
    public function isAlive(Model $notifiable): bool
    {
        return $this->ping($notifiable)->isPong();
    }

    /**
     * {@inheritDoc}
     */
    public function pingOrPrune(Model $notifiable): PingStatus
    {
        $status = $this->ping($notifiable);

        if ($status->isInvalid()) {
            $notifiable->delete();
        }

        return $status;
    }
}
