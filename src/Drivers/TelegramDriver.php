<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\TelegramConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Driver for sending Telegram notifications.
 *
 * Sends messages to Telegram chats using bot API.
 * Supports custom chat IDs and bot tokens for each message.
 */
final class TelegramDriver extends AbstractDriver
{
    private const TELEGRAM_API_URL = 'https://api.telegram.org/bot';

    /**
     * Constructor for the Telegram driver.
     *
     * @param  TelegramConfigRecord  $config  The Telegram configuration
     */
    public function __construct(
        private readonly TelegramConfigRecord $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $metadata = $route->getMetadata();

        $chatId = $metadata?->get('chat_id') ?? $this->config->chat_id;
        $botToken = $this->config->bot_token;

        if (! $chatId || ! $botToken) {
            throw new RuntimeException('Telegram configuration incomplete.');
        }

        $payload = $this->buildPayload($chatId, $message, $metadata);

        $response = Http::post($this->buildApiUrl($botToken), $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Telegram API error: '.$response->body());
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getChannel(): string
    {
        return 'telegram';
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && $this->config->bot_token !== null
            && $this->config->chat_id !== null;
    }

    /**
     * Build the Telegram message payload.
     *
     * @param  string|int  $chatId  The chat ID
     * @param  NotificationMessageVO  $message  The notification message
     * @param  mixed  $metadata  Additional metadata for the message
     * @return array<string, mixed> The formatted Telegram payload
     */
    private function buildPayload(
        string|int $chatId,
        NotificationMessageVO $message,
        mixed $metadata
    ): array {
        $payload = [
            'chat_id' => $chatId,
            'text' => $message->getBodyValue(),
        ];

        // Optional parameters
        $parseMode = $metadata?->get('parse_mode') ?? 'html';
        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }

        $disableNotification = $metadata?->get('disable_notification') ?? false;
        if ($disableNotification) {
            $payload['disable_notification'] = true;
        }

        $replyToMessageId = $metadata?->get('reply_to_message_id');
        if ($replyToMessageId) {
            $payload['reply_to_message_id'] = $replyToMessageId;
        }

        $keyboard = $metadata?->get('keyboard');
        if ($keyboard) {
            $payload['reply_markup'] = json_encode([
                'keyboard' => $keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]);
        }

        return $payload;
    }

    /**
     * Build the Telegram API URL.
     *
     * @param  string  $botToken  The bot token
     * @return string The full API URL
     */
    private function buildApiUrl(string $botToken): string
    {
        return self::TELEGRAM_API_URL.$botToken.'/sendMessage';
    }
}
