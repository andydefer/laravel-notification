<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\TelegramConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Support\Facades\Http;

final class TelegramDriver extends AbstractDriver
{
    public function __construct(
        private readonly TelegramConfigRecord $config,
    ) {}

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $metadata = $route->getMetadata();

        $chatId = $metadata?->get('chat_id') ?? $this->config->chat_id;
        $botToken = $this->config->bot_token;

        if (! $chatId || ! $botToken) {
            throw new \RuntimeException('Telegram configuration incomplete.');
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $response = Http::post($url, [
            'chat_id' => $chatId,
            'text' => $message->getBodyValue(),
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Telegram API error: '.$response->body());
        }

        return true;
    }

    public function getChannel(): string
    {
        return 'telegram';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && $this->config->bot_token !== null
            && $this->config->chat_id !== null;
    }
}
