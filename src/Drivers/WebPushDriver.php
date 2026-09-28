<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\WebPushConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;
use Throwable;

/**
 * Web Push driver using the W3C Web Push protocol with VAPID.
 *
 * Unlike FirebaseCloudMessagingDriver, this driver targets the
 * browser's native Push API. The subscription endpoint, p256dh and
 * auth keys must be provided per destination.
 */
final class WebPushDriver extends AbstractDriver
{
    private const ENDPOINT_KEY = 'endpoint';

    private const P256DH_KEY = 'p256dh';

    private const AUTH_KEY = 'auth';

    private const TITLE_KEY = 'title';

    private const DATA_KEY = 'data';

    public function __construct(
        private readonly WebPushConfigRecord $config,
    ) {}

    public function getChannel(): string
    {
        return 'webpush';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && ! empty($this->config->subject)
            && ! empty($this->config->public_key)
            && ! empty($this->config->private_key);
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        if (! $this->validateConfiguration()) {
            throw new RuntimeException('Web Push configuration is incomplete.');
        }

        $metadata = $route->getMetadata();
        $destination = $route->getDestination();

        $subscription = $this->buildSubscription($destination, $metadata);

        $webPush = $this->createClient();

        $payload = $this->buildPayload($message, $metadata);

        $report = $webPush->sendOneNotification(
            $subscription,
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        if ($report->isSuccess()) {
            return true;
        }

        throw new RuntimeException(sprintf(
            'Web Push trigger failed: %s',
            $report->getReason(),
        ));
    }

    private function buildSubscription(string $destination, mixed $metadata): Subscription
    {
        $endpoint = $metadata?->get(self::ENDPOINT_KEY) ?? $destination;
        $p256dh = $metadata?->get(self::P256DH_KEY);
        $auth = $metadata?->get(self::AUTH_KEY);

        if (! is_string($endpoint) || $endpoint === '') {
            throw new RuntimeException('Web Push endpoint is missing.');
        }

        if (! is_string($p256dh) || $p256dh === '') {
            throw new RuntimeException('Web Push p256dh key is missing.');
        }

        if (! is_string($auth) || $auth === '') {
            throw new RuntimeException('Web Push auth key is missing.');
        }

        return Subscription::create([
            'endpoint' => $endpoint,
            'publicKey' => $p256dh,
            'authToken' => $auth,
        ]);
    }

    private function createClient(): WebPush
    {
        try {
            return new WebPush(
                [
                    'VAPID' => [
                        'subject' => $this->config->subject,
                        'publicKey' => $this->config->public_key,
                        'privateKey' => $this->config->private_key,
                    ],
                ],
                [
                    'TTL' => $this->config->ttl,
                    'urgency' => $this->config->urgency,
                    'topic' => $this->config->topic,
                ],
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf('Web Push client initialization failed: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(NotificationMessageVO $message, mixed $metadata): array
    {
        $payload = [
            'title' => $metadata?->get(self::TITLE_KEY) ?? (string) $message->getSubjectValue(),
            'subject' => (string) $message->getSubjectValue(),
            'body' => (string) $message->getBodyValue(),
            'type' => $message->getType(),
        ];

        $routeData = $metadata?->get(self::DATA_KEY);
        if (is_array($routeData) && $routeData !== []) {
            $payload['data'] = $routeData;
        }

        $messageData = $message->getData()?->toArray() ?? [];
        if ($messageData !== []) {
            $payload['data'] = array_merge($payload['data'] ?? [], $messageData);
        }

        return $payload;
    }
}
