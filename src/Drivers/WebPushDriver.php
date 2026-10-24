<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
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
 * La route porte UNIQUEMENT les identifiants du device :
 *   - endpoint (destination)
 *   - p256dh (metadata)
 *   - auth (metadata)
 *
 * Les options de transport (`TTL`, `urgency`, `topic`) viennent
 * de `$message->getMetadata()` — elles sont communes à tous les devices
 * d'un même envoi.
 */
final class WebPushDriver extends AbstractDriver
{
    // ⬇️ Identifiants device — RESTENT dans la route
    private const ENDPOINT_KEY = 'endpoint';

    private const P256DH_KEY = 'p256dh';

    private const AUTH_KEY = 'auth';

    // ⬇️ Options de transport — PASSENT dans le message
    private const TTL_KEY = 'ttl';

    private const URGENCY_KEY = 'urgency';

    private const TOPIC_KEY = 'topic';

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

        $subscription = $this->buildSubscription(
            $route->getDestination(),
            $route->getMetadata(),
        );

        $webPush = $this->createClient();

        // ⬇️ Options par notification, lues depuis le message
        $options = $this->resolveOptions($message);

        $report = $webPush->sendOneNotification(
            $subscription,
            json_encode($message->toArray(), JSON_THROW_ON_ERROR),
            $options,
        );

        if ($report->isSuccess()) {
            return true;
        }

        throw new RuntimeException(sprintf(
            'Web Push trigger failed: %s',
            $report->getReason(),
        ));
    }

    /**
     * Construit la Subscription depuis la route (endpoint + clés).
     */
    private function buildSubscription(string $destination, ?StrictDataObject $metadata): Subscription
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

    /**
     * Résout les options de transport depuis `$message->getMetadata()`.
     *
     * Priorité :
     *   1. `$message->getMetadata()` — override explicite par message
     *   2. `$this->config` — valeurs par défaut globales
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(NotificationMessageVO $message): array
    {
        $metadata = $message->getMetadata();

        $ttl = $metadata?->get(self::TTL_KEY);
        if (! is_int($ttl) || $ttl < 0) {
            $ttl = $this->config->ttl;
        }

        $urgency = $metadata?->get(self::URGENCY_KEY);
        if (! is_string($urgency) || ! in_array($urgency, ['very-low', 'low', 'normal', 'high'], true)) {
            $urgency = $this->config->urgency;
        }

        $topic = $metadata?->get(self::TOPIC_KEY);
        if (! is_string($topic) || $topic === '') {
            $topic = $this->config->topic;
        }

        return [
            'TTL' => $ttl,
            'urgency' => $urgency,
            'topic' => $topic,
        ];
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
                // ⬇️ Options par défaut (fallback) — surchargées par resolveOptions()
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
}
