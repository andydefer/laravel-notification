<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\SlackConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Driver for sending Slack notifications.
 *
 * Sends messages to Slack channels using incoming webhooks.
 * Supports attachments and custom metadata for rich notifications.
 */
final class SlackDriver extends AbstractDriver
{
    /**
     * Constructor for the Slack driver.
     *
     * @param  SlackConfigRecord  $config  The Slack configuration
     */
    public function __construct(
        private readonly SlackConfigRecord $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $metadata = $route->getMetadata();

        $webhookUrl = $metadata?->get('webhook_url') ?? $this->config->webhook_url;

        if (! $webhookUrl) {
            throw new RuntimeException('Slack webhook URL not specified.');
        }

        $payload = $this->buildPayload($message, $metadata);

        $response = Http::post($webhookUrl, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Slack API error: '.$response->body());
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getChannel(): string
    {
        return 'slack';
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(): bool
    {
        return $this->config->enabled && $this->config->webhook_url !== null;
    }

    /**
     * Build the Slack message payload.
     *
     * @param  NotificationMessageVO  $message  The notification message
     * @param  mixed  $metadata  Additional metadata for the Slack message
     * @return array<string, mixed> The formatted Slack payload
     */
    private function buildPayload(NotificationMessageVO $message, mixed $metadata): array
    {
        $payload = [
            'text' => $message->getBodyValue(),
        ];

        $attachments = $metadata?->get('attachments') ?? [];

        if (! empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }
}
