<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests;

use AndyDefer\LaravelNotification\NotificationServiceProvider;
use AndyDefer\Logger\LoggerServiceProvider;
use AndyDefer\Task\TaskServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    private array $testEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadTestEnvironment();
        $this->setUpEnvironmentVariables();
        $this->setConfigFromEnv();
        $this->clearLogs();
        $this->loadMigrations();
    }

    protected function tearDown(): void
    {
        $this->clearLogs();
        parent::tearDown();
        \Mockery::close();
    }

    /**
     * Load test environment configuration from file or use defaults.
     */
    protected function loadTestEnvironment(): void
    {
        $envFile = __DIR__.'/test_env.php';

        if (file_exists($envFile)) {
            $this->testEnv = require $envFile;
        } else {
            $this->testEnv = $this->getDefaultTestEnvironment();
        }
    }

    /**
     * Get default test environment variables.
     *
     * @return array<string, string> Default test environment variables
     */
    protected function getDefaultTestEnvironment(): array
    {
        return [
            // Mail
            'MAIL_FROM_ADDRESS' => 'noreply@test.com',
            'MAIL_FROM_NAME' => 'Test App',
            'MAIL_DEFAULT_TO' => 'test@example.com',

            // SMS (Twilio)
            'TWILIO_SID' => 'ACtest123456789',
            'TWILIO_TOKEN' => 'testtoken123456789',
            'TWILIO_FROM' => '+1234567890',

            // WhatsApp (Meta)
            'WHATSAPP_ACCESS_TOKEN' => 'test_access_token_123456789',
            'WHATSAPP_PHONE_NUMBER_ID' => '123456789012345',

            // Slack - Faux webhook pour les tests
            'SLACK_WEBHOOK_URL' => 'https://hooks.slack.com/services/fake/fake/fake',

            // Telegram
            'TELEGRAM_BOT_TOKEN' => '1234567890:ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'TELEGRAM_CHAT_ID' => '-123456789',

            // Push (FCM/APNS)
            'FCM_API_KEY' => 'AAAAtest123456789',
            'FCM_PROJECT_ID' => 'test-project-123456',
            'APNS_KEY_PATH' => '/path/to/apns/key.p8',
            'APNS_KEY_ID' => 'ABCDEF1234',
            'APNS_TEAM_ID' => 'ABCDEF1234',
            'APNS_BUNDLE_ID' => 'com.test.app',

            // Logs
            'NOTIFICATION_LOG_CHANNEL' => 'daily',
            'NOTIFICATION_LOG_LEVEL' => 'debug',
        ];
    }

    /**
     * Get an environment variable from test configuration.
     *
     * @param  string  $key  The environment variable key
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The environment variable value
     */
    protected function getEnv(string $key, mixed $default = null): mixed
    {
        return $this->testEnv[$key] ?? $default;
    }

    protected function clearLogs(): void
    {
        $logPath = storage_path('logs/test.log');
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            TaskServiceProvider::class,
            LoggerServiceProvider::class,
            NotificationServiceProvider::class,
        ];
    }

    protected function setUpEnvironmentVariables(): void
    {
        // Mail
        putenv('MAIL_FROM_ADDRESS='.$this->getEnv('MAIL_FROM_ADDRESS'));
        putenv('MAIL_FROM_NAME='.$this->getEnv('MAIL_FROM_NAME'));
        putenv('MAIL_DEFAULT_TO='.$this->getEnv('MAIL_DEFAULT_TO'));

        // SMS (Twilio)
        putenv('TWILIO_SID='.$this->getEnv('TWILIO_SID'));
        putenv('TWILIO_TOKEN='.$this->getEnv('TWILIO_TOKEN'));
        putenv('TWILIO_FROM='.$this->getEnv('TWILIO_FROM'));

        // WhatsApp (Meta)
        putenv('WHATSAPP_ACCESS_TOKEN='.$this->getEnv('WHATSAPP_ACCESS_TOKEN'));
        putenv('WHATSAPP_PHONE_NUMBER_ID='.$this->getEnv('WHATSAPP_PHONE_NUMBER_ID'));

        // Slack
        putenv('SLACK_WEBHOOK_URL='.$this->getEnv('SLACK_WEBHOOK_URL'));

        // Telegram
        putenv('TELEGRAM_BOT_TOKEN='.$this->getEnv('TELEGRAM_BOT_TOKEN'));
        putenv('TELEGRAM_CHAT_ID='.$this->getEnv('TELEGRAM_CHAT_ID'));

        // Push (FCM/APNS)
        putenv('FCM_API_KEY='.$this->getEnv('FCM_API_KEY'));
        putenv('FCM_PROJECT_ID='.$this->getEnv('FCM_PROJECT_ID'));
        putenv('APNS_KEY_PATH='.$this->getEnv('APNS_KEY_PATH'));
        putenv('APNS_KEY_ID='.$this->getEnv('APNS_KEY_ID'));
        putenv('APNS_TEAM_ID='.$this->getEnv('APNS_TEAM_ID'));
        putenv('APNS_BUNDLE_ID='.$this->getEnv('APNS_BUNDLE_ID'));

        // Logs
        putenv('NOTIFICATION_LOG_CHANNEL='.$this->getEnv('NOTIFICATION_LOG_CHANNEL'));
        putenv('NOTIFICATION_LOG_LEVEL='.$this->getEnv('NOTIFICATION_LOG_LEVEL'));

        // Rendre les variables disponibles dans $_ENV
        foreach ($this->testEnv as $key => $value) {
            $_ENV[$key] = $value;
        }
    }

    protected function setConfigFromEnv(): void
    {
        $config = $this->app['config'];

        // Notification channels
        $config->set('notification.channels.mail', [
            'enabled' => true,
            'driver' => 'mail',
            'default_to' => $this->getEnv('MAIL_DEFAULT_TO', 'test@example.com'),
            'default_from' => $this->getEnv('MAIL_FROM_ADDRESS', 'noreply@test.com'),
            'default_from_name' => $this->getEnv('MAIL_FROM_NAME', 'Test App'),
        ]);

        $config->set('notification.channels.sms', [
            'enabled' => true,
            'driver' => 'twilio',
            'sid' => $this->getEnv('TWILIO_SID', 'ACtest123456789'),
            'token' => $this->getEnv('TWILIO_TOKEN', 'testtoken123456789'),
            'from' => $this->getEnv('TWILIO_FROM', '+1234567890'),
        ]);

        $config->set('notification.channels.whatsapp', [
            'enabled' => true,
            'driver' => 'meta',
            'access_token' => $this->getEnv('WHATSAPP_ACCESS_TOKEN', 'test_access_token_123456789'),
            'phone_number_id' => $this->getEnv('WHATSAPP_PHONE_NUMBER_ID', '123456789012345'),
        ]);

        $config->set('notification.channels.slack', [
            'enabled' => true,
            'webhook_url' => $this->getEnv('SLACK_WEBHOOK_URL', 'https://hooks.slack.com/services/fake/fake/fake'),
        ]);

        $config->set('notification.channels.telegram', [
            'enabled' => true,
            'bot_token' => $this->getEnv('TELEGRAM_BOT_TOKEN', '1234567890:ABCDEFGHIJKLMNOPQRSTUVWXYZ'),
            'chat_id' => $this->getEnv('TELEGRAM_CHAT_ID', '-123456789'),
        ]);

        $config->set('notification.channels.push', [
            'enabled' => true,
            'platform' => 'fcm',
            'fcm_api_key' => $this->getEnv('FCM_API_KEY', 'AAAAtest123456789'),
            'fcm_project_id' => $this->getEnv('FCM_PROJECT_ID', 'test-project-123456'),
            'apns_key_path' => $this->getEnv('APNS_KEY_PATH', '/path/to/apns/key.p8'),
            'apns_key_id' => $this->getEnv('APNS_KEY_ID', 'ABCDEF1234'),
            'apns_team_id' => $this->getEnv('APNS_TEAM_ID', 'ABCDEF1234'),
            'apns_bundle_id' => $this->getEnv('APNS_BUNDLE_ID', 'com.test.app'),
            'default_sound' => 'default',
            'default_tokens' => [],
        ]);

        $config->set('notification.channels.database', [
            'driver' => 'database',
            'table' => 'notifications',
        ]);

        $config->set('notification.default_channels', ['mail', 'database']);

        $config->set('notification.logging', [
            'enabled' => true,
            'channel' => $this->getEnv('NOTIFICATION_LOG_CHANNEL', 'daily'),
            'level' => $this->getEnv('NOTIFICATION_LOG_LEVEL', 'debug'),
        ]);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        // Configurer le mailer pour utiliser le driver 'log'
        $app['config']->set('mail.default', 'log');
        $app['config']->set('mail.mailers.log', [
            'transport' => 'log',
            'channel' => 'single',
        ]);

        // Configuration des logs
        $app['config']->set('logging.default', 'stack');
        $app['config']->set('logging.channels.stack', [
            'driver' => 'stack',
            'channels' => ['single'],
        ]);
        $app['config']->set('logging.channels.single', [
            'driver' => 'single',
            'path' => storage_path('logs/test.log'),
            'level' => 'debug',
        ]);
    }

    protected function loadMigrations(): void
    {
        $testMigrationsPath = __DIR__.'/Fixtures/migrations';
        $packageMigrationsPath = __DIR__.'/../database/migrations';

        if (is_dir($packageMigrationsPath)) {
            $this->loadMigrationsFrom($packageMigrationsPath);
        }

        if (is_dir($testMigrationsPath)) {
            $this->loadMigrationsFrom($testMigrationsPath);
        }
    }
}
