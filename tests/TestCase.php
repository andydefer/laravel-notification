<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests;

use AndyDefer\Actions\ActionServiceProvider;
use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\LaravelNotification\NotificationServiceProvider;
use AndyDefer\Logger\LoggerServiceProvider;
use AndyDefer\Nemesis\NemesisServiceProvider;
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

    protected function loadTestEnvironment(): void
    {
        $envFile = __DIR__.'/test_env.php';

        if (file_exists($envFile)) {
            $this->testEnv = require $envFile;
        } else {
            $this->testEnv = $this->getDefaultTestEnvironment();
        }
    }

    protected function getDefaultTestEnvironment(): array
    {
        return [
            'MAIL_FROM_ADDRESS' => 'noreply@test.com',
            'MAIL_FROM_NAME' => 'Test App',
            'MAIL_DEFAULT_TO' => 'test@example.com',

            'TWILIO_SID' => 'ACtest123456789',
            'TWILIO_TOKEN' => 'testtoken123456789',
            'TWILIO_FROM' => '+1234567890',

            'WHATSAPP_ACCESS_TOKEN' => 'test_access_token_123456789',
            'WHATSAPP_PHONE_NUMBER_ID' => '123456789012345',

            'SLACK_WEBHOOK_URL' => 'https://hooks.slack.com/services/fake/fake/fake',

            'TELEGRAM_BOT_TOKEN' => '1234567890:ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'TELEGRAM_CHAT_ID' => '-123456789',

            'FCM_API_KEY' => 'AAAAtest123456789',
            'FCM_PROJECT_ID' => 'test-project-123456',
            'APNS_KEY_PATH' => '/path/to/apns/key.p8',
            'APNS_KEY_ID' => 'ABCDEF1234',
            'APNS_TEAM_ID' => 'ABCDEF1234',
            'APNS_BUNDLE_ID' => 'com.test.app',

            'PUSHER_APP_ID' => '123456',
            'PUSHER_APP_KEY' => 'test-pusher-key',
            'PUSHER_APP_SECRET' => 'test-pusher-secret',
            'PUSHER_APP_CLUSTER' => 'eu',
            'PUSHER_USE_TLS' => 'true',
            'PUSHER_TIMEOUT' => '30',
            'PUSHER_NOTIFICATION_CHANNEL' => 'notifications',

            'NOTIFICATION_LOG_CHANNEL' => 'daily',
            'NOTIFICATION_LOG_LEVEL' => 'debug',
        ];
    }

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
            DirectiveServiceProvider::class,
            TaskServiceProvider::class,
            LoggerServiceProvider::class,
            ActionServiceProvider::class,
            NemesisServiceProvider::class,
            NotificationServiceProvider::class,
        ];
    }

    protected function setUpEnvironmentVariables(): void
    {
        putenv('MAIL_FROM_ADDRESS='.$this->getEnv('MAIL_FROM_ADDRESS'));
        putenv('MAIL_FROM_NAME='.$this->getEnv('MAIL_FROM_NAME'));
        putenv('MAIL_DEFAULT_TO='.$this->getEnv('MAIL_DEFAULT_TO'));

        putenv('TWILIO_SID='.$this->getEnv('TWILIO_SID'));
        putenv('TWILIO_TOKEN='.$this->getEnv('TWILIO_TOKEN'));
        putenv('TWILIO_FROM='.$this->getEnv('TWILIO_FROM'));

        putenv('WHATSAPP_ACCESS_TOKEN='.$this->getEnv('WHATSAPP_ACCESS_TOKEN'));
        putenv('WHATSAPP_PHONE_NUMBER_ID='.$this->getEnv('WHATSAPP_PHONE_NUMBER_ID'));

        putenv('SLACK_WEBHOOK_URL='.$this->getEnv('SLACK_WEBHOOK_URL'));

        putenv('TELEGRAM_BOT_TOKEN='.$this->getEnv('TELEGRAM_BOT_TOKEN'));
        putenv('TELEGRAM_CHAT_ID='.$this->getEnv('TELEGRAM_CHAT_ID'));

        putenv('FCM_API_KEY='.$this->getEnv('FCM_API_KEY'));
        putenv('FCM_PROJECT_ID='.$this->getEnv('FCM_PROJECT_ID'));
        putenv('APNS_KEY_PATH='.$this->getEnv('APNS_KEY_PATH'));
        putenv('APNS_KEY_ID='.$this->getEnv('APNS_KEY_ID'));
        putenv('APNS_TEAM_ID='.$this->getEnv('APNS_TEAM_ID'));
        putenv('APNS_BUNDLE_ID='.$this->getEnv('APNS_BUNDLE_ID'));

        putenv('PUSHER_APP_ID='.$this->getEnv('PUSHER_APP_ID'));
        putenv('PUSHER_APP_KEY='.$this->getEnv('PUSHER_APP_KEY'));
        putenv('PUSHER_APP_SECRET='.$this->getEnv('PUSHER_APP_SECRET'));
        putenv('PUSHER_APP_CLUSTER='.$this->getEnv('PUSHER_APP_CLUSTER'));
        putenv('PUSHER_USE_TLS='.$this->getEnv('PUSHER_USE_TLS'));
        putenv('PUSHER_TIMEOUT='.$this->getEnv('PUSHER_TIMEOUT'));
        putenv('PUSHER_NOTIFICATION_CHANNEL='.$this->getEnv('PUSHER_NOTIFICATION_CHANNEL'));

        putenv('NOTIFICATION_LOG_CHANNEL='.$this->getEnv('NOTIFICATION_LOG_CHANNEL'));
        putenv('NOTIFICATION_LOG_LEVEL='.$this->getEnv('NOTIFICATION_LOG_LEVEL'));

        foreach ($this->testEnv as $key => $value) {
            $_ENV[$key] = $value;
        }
    }

    protected function setConfigFromEnv(): void
    {
        $config = $this->app['config'];

        $config->set('notification.channels.mail', [
            'enabled' => true,
            'driver' => 'mail',
            'default_to' => $this->getEnv('MAIL_DEFAULT_TO', 'test@example.com'),
            'default_from' => $this->getEnv('MAIL_FROM_ADDRESS', 'noreply@test.com'),
            'default_from_name' => $this->getEnv('MAIL_FROM_NAME', 'Test App'),
        ]);

        $config->set('notification.channels.firebase', [
            // Server-side (admin SDK)
            'enabled' => filter_var(
                $this->getEnv('FIREBASE_NOTIFICATION_ENABLED', 'true'),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'credentials_path' => $this->getEnv('FIREBASE_CREDENTIALS_PATH'),
            'project_id' => $this->getEnv('FIREBASE_PROJECT_ID'),
            'scope' => $this->getEnv(
                'FIREBASE_SCOPE',
                'https://www.googleapis.com/auth/firebase.messaging',
            ),
            'timeout' => (int) $this->getEnv('FIREBASE_TIMEOUT', '30'),

            // Client-side (public config)
            'api_key' => $this->getEnv('FIREBASE_API_KEY'),
            'auth_domain' => $this->getEnv('FIREBASE_AUTH_DOMAIN'),
            'storage_bucket' => $this->getEnv('FIREBASE_STORAGE_BUCKET'),
            'messaging_sender_id' => $this->getEnv('FIREBASE_MESSAGING_SENDER_ID'),
            'app_id' => $this->getEnv('FIREBASE_APP_ID'),
            'measurement_id' => $this->getEnv('FIREBASE_MEASUREMENT_ID'),
            'vapid_key' => $this->getEnv('FIREBASE_VAPID_KEY'),
            'legacy_key' => $this->getEnv('FIREBASE_KEY'),
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

        $config->set('notification.channels.pusher', [
            'enabled' => true,
            'app_id' => $this->getEnv('PUSHER_APP_ID', '2197533'),
            'key' => $this->getEnv('PUSHER_APP_KEY', 'f1bc1733c3f2f2e30cdd'),
            'secret' => $this->getEnv('PUSHER_APP_SECRET', '2613a0ff0eddf57f8d6f'),
            'cluster' => $this->getEnv('PUSHER_APP_CLUSTER', 'ap2'),
            'use_tls' => filter_var($this->getEnv('PUSHER_USE_TLS', 'true'), FILTER_VALIDATE_BOOLEAN),
            'timeout' => (int) $this->getEnv('PUSHER_TIMEOUT', '30'),
            'default_channel' => $this->getEnv('PUSHER_NOTIFICATION_CHANNEL', 'notifications'),
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

        $app['config']->set('mail.default', 'log');
        $app['config']->set('mail.mailers.log', [
            'transport' => 'log',
            'channel' => 'single',
        ]);

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
        $nemesisMigrationsPath = Paths::packageRoot().'/../laravel-nemesis/database/migrations';

        if (is_dir($packageMigrationsPath)) {
            $this->loadMigrationsFrom($packageMigrationsPath);
        }

        if (is_dir($nemesisMigrationsPath)) {
            $this->loadMigrationsFrom($nemesisMigrationsPath);
        }

        if (is_dir($testMigrationsPath)) {
            $this->loadMigrationsFrom($testMigrationsPath);
        }
    }
}
