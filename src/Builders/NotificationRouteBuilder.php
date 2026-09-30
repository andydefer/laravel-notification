<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Builders;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Channels\WebPushChannel;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\LaravelNotification\ValueObjects\PusherChannelNameVO;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Fluent builder for NotificationRouteCollection.
 *
 * Encapsulates the repetitive route construction shared by every notifiable
 * model (mail, database, fcm devices, web push subscriptions, pusher channels).
 *
 * Guarantees uniqueness per (channel, destination) pair: adding the same
 * route twice is a no-op.
 */
final class NotificationRouteBuilder
{
    private NotificationRouteCollection $collection;

    private function __construct()
    {
        $this->collection = new NotificationRouteCollection;
    }

    public static function make(): self
    {
        return new self;
    }

    // ============================================================
    // MAIL
    // ============================================================

    public function addMail(?string $email, array $metadata = []): self
    {
        if ($email === null || $email === '') {
            return $this;
        }

        if ($this->has(MailChannel::class, $email)) {
            return $this;
        }

        $this->collection->add(new NotificationRouteVO(
            channelClass: MailChannel::class,
            destination: $email,
            metadata: new StrictDataObject($metadata),
        ));

        return $this;
    }

    /**
     * @param  iterable<string>  $emails
     */
    public function addMails(iterable $emails, array $metadata = []): self
    {
        foreach ($emails as $email) {
            if (! is_string($email)) {
                throw new InvalidArgumentException('Emails must be strings.');
            }

            $this->addMail($email, $metadata);
        }

        return $this;
    }

    // ============================================================
    // DATABASE
    // ============================================================

    public function addDatabase(): self
    {
        if ($this->has(DatabaseChannel::class, 'database')) {
            return $this;
        }

        $this->collection->add(new NotificationRouteVO(
            channelClass: DatabaseChannel::class,
            destination: 'database',
        ));

        return $this;
    }

    // ============================================================
    // FCM
    // ============================================================

    public function addFcm(FcmDevice $device, array $metadata = []): self
    {
        $token = (string) $device->token;

        if ($token === '') {
            return $this;
        }

        if ($this->has(FirebaseCloudMessagingChannel::class, $token)) {
            return $this;
        }

        $this->collection->add(new NotificationRouteVO(
            channelClass: FirebaseCloudMessagingChannel::class,
            destination: $token,
            metadata: new StrictDataObject([
                'type' => 'fcm',
                'device_id' => (string) $device->device_id,
                ...$metadata,
            ]),
        ));

        return $this;
    }

    /**
     * @param  iterable<FcmDevice>  $devices
     */
    public function addFcms(iterable $devices, array $metadata = []): self
    {
        foreach ($devices as $device) {
            if (! $device instanceof FcmDevice) {
                throw new InvalidArgumentException(
                    sprintf('Expected an instance of %s.', FcmDevice::class),
                );
            }

            $this->addFcm($device, $metadata);
        }

        return $this;
    }

    // ============================================================
    // WEB PUSH
    // ============================================================

    public function addWebPush(WebPushSubscription $subscription, array $metadata = []): self
    {
        $endpoint = (string) $subscription->endpoint;
        $p256dh = (string) $subscription->p256dh;
        $auth = (string) $subscription->auth;

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return $this;
        }

        if ($this->has(WebPushChannel::class, $endpoint)) {
            return $this;
        }

        $this->collection->add(new NotificationRouteVO(
            channelClass: WebPushChannel::class,
            destination: $endpoint,
            metadata: new StrictDataObject([
                'type' => 'webpush',
                'endpoint' => $endpoint,
                'p256dh' => $p256dh,
                'auth' => $auth,
                ...$metadata,
            ]),
        ));

        return $this;
    }

    /**
     * @param  iterable<WebPushSubscription>  $subscriptions
     */
    public function addWebPushes(iterable $subscriptions, array $metadata = []): self
    {
        foreach ($subscriptions as $subscription) {
            if (! $subscription instanceof WebPushSubscription) {
                throw new InvalidArgumentException(
                    sprintf('Expected an instance of %s.', WebPushSubscription::class),
                );
            }

            $this->addWebPush($subscription, $metadata);
        }

        return $this;
    }

    // ============================================================
    // PUSHER
    // ============================================================

    public function addPusher(string $channel, string $event = 'notification'): self
    {
        if ($channel === '') {
            return $this;
        }

        if ($this->has(PusherChannel::class, $channel)) {
            return $this;
        }

        $this->collection->add(new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: $channel,
            metadata: new StrictDataObject([
                'type' => 'pusher',
                'channel' => $channel,
                'event' => $event,
            ]),
        ));

        return $this;
    }

    public function addPusherForModel(Model $model, string $event = 'notification'): self
    {
        $channel = PusherChannelNameVO::forModel($model)->getValue();

        return $this->addPusher($channel, $event);
    }

    // ============================================================
    // BUILD
    // ============================================================

    public function build(): NotificationRouteCollection
    {
        return $this->collection;
    }

    // ============================================================
    // PRIVATE
    // ============================================================

    /**
     * Determine whether a route already exists for the given channel/destination pair.
     */
    private function has(string $channelClass, string $destination): bool
    {
        foreach ($this->collection as $route) {
            if (! $route instanceof NotificationRouteVO) {
                continue;
            }

            if ($route->getChannelClass() !== $channelClass) {
                continue;
            }

            if ($route->getDestination() !== $destination) {
                continue;
            }

            return true;
        }

        return false;
    }
}
