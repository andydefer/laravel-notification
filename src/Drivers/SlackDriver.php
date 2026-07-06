<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\SlackConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Support\Facades\Http;

final class SlackDriver extends AbstractDriver
{
    public function __construct(
        private readonly SlackConfigRecord $config,
    ) {}

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $metadata = $route->getMetadata();

        $webhookUrl = $metadata?->get('webhook_url') ?? $this->config->webhook_url;

        if (! $webhookUrl) {
            throw new \RuntimeException('Slack webhook URL not specified.');
        }

        $response = Http::post($webhookUrl, [
            'text' => $message->getBodyValue(),
            'attachments' => $metadata?->get('attachments') ?? [],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Slack API error: '.$response->body());
        }

        return true;
    }

    public function getChannel(): string
    {
        return 'slack';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->enabled && $this->config->webhook_url !== null;
    }
}
