<?php

// src/Configs/NotificationConfig.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Configs;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Contracts\Configs\NotificationConfigInterface;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;
use AndyDefer\LaravelNotification\Records\FirebaseConfigRecord;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\Records\PushConfigRecord;
use AndyDefer\LaravelNotification\Records\PusherConfigRecord;
use AndyDefer\LaravelNotification\Records\SlackConfigRecord;
use AndyDefer\LaravelNotification\Records\SmsConfigRecord;
use AndyDefer\LaravelNotification\Records\TelegramConfigRecord;
use AndyDefer\LaravelNotification\Records\WhatsAppConfigRecord;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class NotificationConfig implements NotificationConfigInterface
{
    private const DEFAULT_CHANNELS = ['mail', 'database'];

    private const DEFAULT_MAIL_CONFIG = [
        'enabled' => true,
        'driver' => 'mail',
        'default_from' => null,
        'default_from_name' => null,
        'default_to' => null,
    ];

    private const DEFAULT_FIREBASE_CONFIG = [
        'enabled' => false,
        'credentials_path' => null,
        'project_id' => null,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'timeout' => 30,
    ];

    private const DEFAULT_DATABASE_CONFIG = [
        'driver' => 'database',
        'table' => 'notifications',
    ];

    private const DEFAULT_SMS_CONFIG = [
        'enabled' => false,
        'driver' => 'twilio',
        'sid' => null,
        'token' => null,
        'from' => null,
    ];

    private const DEFAULT_SLACK_CONFIG = [
        'enabled' => false,
        'webhook_url' => null,
    ];

    private const DEFAULT_WHATSAPP_CONFIG = [
        'enabled' => false,
        'driver' => 'meta',
        'access_token' => null,
        'phone_number_id' => null,
    ];

    private const DEFAULT_TELEGRAM_CONFIG = [
        'enabled' => false,
        'bot_token' => null,
        'chat_id' => null,
    ];

    private const DEFAULT_PUSH_CONFIG = [
        'enabled' => false,
        'platform' => 'fcm',
        'fcm_api_key' => null,
        'fcm_project_id' => null,
        'apns_key_path' => null,
        'apns_key_id' => null,
        'apns_team_id' => null,
        'apns_bundle_id' => null,
        'default_sound' => 'default',
        'default_tokens' => [],
    ];

    private const DEFAULT_PUSHER_CONFIG = [
        'enabled' => false,
        'app_id' => null,
        'key' => null,
        'secret' => null,
        'cluster' => 'eu',
        'use_tls' => true,
        'timeout' => 30,
        'default_channel' => 'notifications',
    ];

    private const DEFAULT_LOGGING_CONFIG = [
        'enabled' => true,
        'channel' => 'daily',
        'level' => 'info',
    ];

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getDefaultChannels(): array
    {
        return $this->config->get('notification.default_channels', self::DEFAULT_CHANNELS);
    }

    /**
     * {@inheritDoc}
     */
    public function getMailConfig(): MailConfigRecord
    {
        $config = $this->config->get('notification.channels.mail', self::DEFAULT_MAIL_CONFIG);

        return MailConfigRecord::from($config);
    }

    /**
     * {@inheritDoc}
     */
    public function getDatabaseConfig(): DatabaseConfigRecord
    {
        $config = $this->config->get('notification.channels.database', self::DEFAULT_DATABASE_CONFIG);

        return DatabaseConfigRecord::from($config);
    }

    /**
     * {@inheritDoc}
     */
    public function getSmsConfig(): SmsConfigRecord
    {
        $config = $this->config->get('notification.channels.sms', self::DEFAULT_SMS_CONFIG);

        return SmsConfigRecord::from($config);
    }

    public function getFirebaseConfig(): FirebaseConfigRecord
    {
        $config = $this->config->get('notification.channels.firebase', self::DEFAULT_FIREBASE_CONFIG);

        return FirebaseConfigRecord::from([
            'enabled' => (bool) ($config['enabled'] ?? false),
            'credentials_path' => $config['credentials_path'] ?? null,
            'project_id' => $config['project_id'] ?? null,
            'scope' => (string) ($config['scope'] ?? 'https://www.googleapis.com/auth/firebase.messaging'),
            'timeout' => (int) ($config['timeout'] ?? 30),
        ]);
    }

    public function isFirebaseEnabled(): bool
    {
        return $this->getFirebaseConfig()->enabled;
    }

    /**
     * {@inheritDoc}
     */
    public function getSlackConfig(): SlackConfigRecord
    {
        $config = $this->config->get('notification.channels.slack', self::DEFAULT_SLACK_CONFIG);

        return SlackConfigRecord::from($config);
    }

    /**
     * {@inheritDoc}
     */
    public function getWhatsAppConfig(): WhatsAppConfigRecord
    {
        $config = $this->config->get('notification.channels.whatsapp', self::DEFAULT_WHATSAPP_CONFIG);

        return WhatsAppConfigRecord::from($config);
    }

    /**
     * {@inheritDoc}
     */
    public function getTelegramConfig(): TelegramConfigRecord
    {
        $config = $this->config->get('notification.channels.telegram', self::DEFAULT_TELEGRAM_CONFIG);

        return TelegramConfigRecord::from($config);
    }

    /**
     * {@inheritDoc}
     */
    public function getPushConfig(): PushConfigRecord
    {
        $config = $this->config->get('notification.channels.push', self::DEFAULT_PUSH_CONFIG);

        // Convertir default_tokens en StrictDataObject si c'est un array
        if (isset($config['default_tokens']) && is_array($config['default_tokens'])) {
            $config['default_tokens'] = new StrictDataObject($config['default_tokens']);
        }

        return PushConfigRecord::from($config);
    }

    /**
     * {@inheritDoc}
     */
    public function getPusherConfig(): PusherConfigRecord
    {
        $config = $this->config->get('notification.channels.pusher', self::DEFAULT_PUSHER_CONFIG);

        return PusherConfigRecord::from($config);
    }

    /**
     * {@inheritDoc}
     */
    public function isSmsEnabled(): bool
    {
        return $this->getSmsConfig()->enabled;
    }

    /**
     * {@inheritDoc}
     */
    public function isWhatsAppEnabled(): bool
    {
        return $this->getWhatsAppConfig()->enabled;
    }

    /**
     * {@inheritDoc}
     */
    public function isSlackEnabled(): bool
    {
        return $this->getSlackConfig()->enabled;
    }

    /**
     * {@inheritDoc}
     */
    public function isTelegramEnabled(): bool
    {
        return $this->getTelegramConfig()->enabled;
    }

    /**
     * {@inheritDoc}
     */
    public function isPushEnabled(): bool
    {
        return $this->getPushConfig()->enabled;
    }

    /**
     * {@inheritDoc}
     */
    public function isPusherEnabled(): bool
    {
        return $this->getPusherConfig()->enabled;
    }

    /**
     * {@inheritDoc}
     */
    public function getLoggingConfig(): array
    {
        return $this->config->get('notification.logging', self::DEFAULT_LOGGING_CONFIG);
    }

    /**
     * {@inheritDoc}
     */
    public function isLoggingEnabled(): bool
    {
        return $this->config->get('notification.logging.enabled', self::DEFAULT_LOGGING_CONFIG['enabled']);
    }

    /**
     * {@inheritDoc}
     */
    public function getEnabledChannels(): array
    {
        $channels = [];

        if ($this->getMailConfig()->enabled) {
            $channels[] = 'mail';
        }

        $databaseTable = $this->getDatabaseConfig()->table;
        if ($databaseTable !== '' && $databaseTable !== null) {
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

        if ($this->isPusherEnabled()) {
            $channels[] = 'pusher';
        }

        return $channels;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllChannels(): array
    {
        $channels = $this->config->get('notification.channels', []);

        return array_keys($channels);
    }
}
