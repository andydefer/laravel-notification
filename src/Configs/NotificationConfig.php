<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Configs;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\Records\PushConfigRecord;
use AndyDefer\LaravelNotification\Records\SlackConfigRecord;
use AndyDefer\LaravelNotification\Records\SmsConfigRecord;
use AndyDefer\LaravelNotification\Records\TelegramConfigRecord;
use AndyDefer\LaravelNotification\Records\WhatsAppConfigRecord;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class NotificationConfig
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function getDefaultChannels(): array
    {
        return $this->config->get('notification.default_channels', ['mail', 'database']);
    }

    public function getMailConfig(): MailConfigRecord
    {
        $config = $this->config->get('notification.channels.mail', [
            'enabled' => true,
            'driver' => 'mail',
            'default_to' => env('MAIL_FROM_ADDRESS'),
            'default_from' => env('MAIL_FROM_ADDRESS'),
            'default_from_name' => env('MAIL_FROM_NAME'),
        ]);

        return MailConfigRecord::from($config);
    }

    public function getDatabaseConfig(): DatabaseConfigRecord
    {
        $config = $this->config->get('notification.channels.database', [
            'driver' => 'database',
            'table' => 'notifications',
        ]);

        return DatabaseConfigRecord::from($config);
    }

    public function getSmsConfig(): SmsConfigRecord
    {
        $config = $this->config->get('notification.channels.sms', [
            'enabled' => false,
            'driver' => 'twilio',
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ]);

        return SmsConfigRecord::from($config);
    }

    public function getSlackConfig(): SlackConfigRecord
    {
        $config = $this->config->get('notification.channels.slack', [
            'enabled' => false,
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ]);

        return SlackConfigRecord::from($config);
    }

    public function getWhatsAppConfig(): WhatsAppConfigRecord
    {
        $config = $this->config->get('notification.channels.whatsapp', [
            'enabled' => false,
            'driver' => 'meta',
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        ]);

        return WhatsAppConfigRecord::from($config);
    }

    public function getTelegramConfig(): TelegramConfigRecord
    {
        $config = $this->config->get('notification.channels.telegram', [
            'enabled' => false,
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ]);

        return TelegramConfigRecord::from($config);
    }

    public function getPushConfig(): PushConfigRecord
    {
        $config = $this->config->get('notification.channels.push', [
            'enabled' => false,
            'platform' => 'fcm',
            'fcm_api_key' => env('FCM_API_KEY'),
            'fcm_project_id' => env('FCM_PROJECT_ID'),
            'apns_key_path' => env('APNS_KEY_PATH'),
            'apns_key_id' => env('APNS_KEY_ID'),
            'apns_team_id' => env('APNS_TEAM_ID'),
            'apns_bundle_id' => env('APNS_BUNDLE_ID'),
            'default_sound' => 'default',
            'default_tokens' => [],
        ]);

        // Convertir default_tokens en StrictDataObject si c'est un array
        if (isset($config['default_tokens']) && is_array($config['default_tokens'])) {
            $config['default_tokens'] = new StrictDataObject($config['default_tokens']);
        }

        return PushConfigRecord::from($config);
    }

    public function isSmsEnabled(): bool
    {
        return $this->getSmsConfig()->enabled;
    }

    public function isWhatsAppEnabled(): bool
    {
        return $this->getWhatsAppConfig()->enabled;
    }

    public function isSlackEnabled(): bool
    {
        return $this->getSlackConfig()->enabled;
    }

    public function isTelegramEnabled(): bool
    {
        return $this->getTelegramConfig()->enabled;
    }

    public function isPushEnabled(): bool
    {
        return $this->getPushConfig()->enabled;
    }

    public function getLoggingConfig(): array
    {
        return $this->config->get('notification.logging', [
            'enabled' => true,
            'channel' => env('NOTIFICATION_LOG_CHANNEL', 'daily'),
            'level' => env('NOTIFICATION_LOG_LEVEL', 'info'),
        ]);
    }

    public function isLoggingEnabled(): bool
    {
        return $this->config->get('notification.logging.enabled', true);
    }

    public function getEnabledChannels(): array
    {
        $channels = [];

        if ($this->getMailConfig()->enabled) {
            $channels[] = 'mail';
        }
        if ($this->getDatabaseConfig()->table !== '') {
            $channels[] = 'database';
        }
        if ($this->isSmsEnabled()) {
            $channels[] = 'sms';
        }
        if ($this->isWhatsAppEnabled()) {
            $channels[] = 'whatsapp';
        }
        if ($this->isSlackEnabled()) {
            $channels[] = 'slack';
        }
        if ($this->isTelegramEnabled()) {
            $channels[] = 'telegram';
        }
        if ($this->isPushEnabled()) {
            $channels[] = 'push';
        }

        return $channels;
    }

    public function getAllChannels(): array
    {
        return array_keys($this->config->get('notification.channels', []));
    }
}
