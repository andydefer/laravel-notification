<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Drivers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Drivers\FirebaseCloudMessagingDriver;
use AndyDefer\LaravelNotification\Records\FirebaseConfigRecord;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class FirebaseCloudMessagingDriverTest extends TestCase
{
    private string $deviceToken;

    private FirebaseCloudMessagingDriver $driver;

    private FirebaseConfigRecord $config;

    private NotificationRouteVO $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceToken = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        $this->config = new FirebaseConfigRecord(
            enabled: true,
            credentials_path: (string) $this->getEnv('FIREBASE_CREDENTIALS_PATH'),
            project_id: (string) $this->getEnv('FIREBASE_PROJECT_ID'),
            scope: 'https://www.googleapis.com/auth/firebase.messaging',
            timeout: (int) $this->getEnv('FIREBASE_TIMEOUT'),
        );

        $this->driver = new FirebaseCloudMessagingDriver($this->config);

        $this->route = new NotificationRouteVO(
            channelClass: FirebaseCloudMessagingChannel::class,
            destination: $this->deviceToken,
            metadata: new StrictDataObject([
                'title' => 'Test Title',
                'data' => ['screen' => 'profile', 'user_id' => '42'],
            ]),
        );
    }

    public function test_get_channel_returns_firebase(): void
    {
        $this->assertEquals('firebase', $this->driver->getChannel());
    }

    public function test_validate_configuration_with_valid_config(): void
    {
        $this->assertTrue($this->driver->validateConfiguration());
    }

    public function test_validate_configuration_when_disabled(): void
    {
        $config = new FirebaseConfigRecord(
            enabled: false,
            credentials_path: (string) $this->getEnv('FIREBASE_CREDENTIALS_PATH'),
            project_id: (string) $this->getEnv('FIREBASE_PROJECT_ID'),
        );
        $driver = new FirebaseCloudMessagingDriver($config);

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_validate_configuration_without_credentials_path(): void
    {
        $config = new FirebaseConfigRecord(
            enabled: true,
            credentials_path: null,
            project_id: (string) $this->getEnv('FIREBASE_PROJECT_ID'),
        );
        $driver = new FirebaseCloudMessagingDriver($config);

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_validate_configuration_with_missing_file(): void
    {
        $config = new FirebaseConfigRecord(
            enabled: true,
            credentials_path: '/nonexistent/path/firebase.json',
            project_id: (string) $this->getEnv('FIREBASE_PROJECT_ID'),
        );
        $driver = new FirebaseCloudMessagingDriver($config);

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_execute_with_empty_configuration_throws_exception(): void
    {
        $config = new FirebaseConfigRecord(
            enabled: true,
            credentials_path: null,
            project_id: null,
        );
        $driver = new FirebaseCloudMessagingDriver($config);

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Driver AndyDefer\LaravelNotification\Drivers\FirebaseCloudMessagingDriver configuration is invalid.',
        );

        $driver->send($message, $this->route);
    }

    public function test_execute_sends_push_notification(): void
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Ceci est un message envoyé depuis du PHP pur.'),
            subject: new MessageSubjectVO('Bonjour ! 👋'),
            type: 'test',
            data: new StrictDataObject([
                'screen' => 'profile',
                'user_id' => '42',
            ]),
        );

        $result = $this->driver->send($message, $this->route);

        $this->assertTrue($result->success, sprintf(
            'Firebase send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertEquals(FirebaseCloudMessagingChannel::class, $result->channel->getValue());
        $this->assertEquals($this->deviceToken, $result->destination);
        $this->assertNull($result->error_message);
    }

    public function test_execute_sends_push_notification_with_metadata_data(): void
    {
        $route = new NotificationRouteVO(
            channelClass: FirebaseCloudMessagingChannel::class,
            destination: $this->deviceToken,
            metadata: new StrictDataObject([
                'title' => 'Metadata Title',
                'data' => [
                    'screen' => 'orders',
                    'order_id' => '99',
                ],
            ]),
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Notification avec metadata.'),
            subject: new MessageSubjectVO('Fallback subject'),
            type: 'order_notification',
        );

        $result = $this->driver->send($message, $route);

        $this->assertTrue($result->success, sprintf(
            'Firebase send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertNull($result->error_message);
    }

    public function test_execute_saves_error_when_device_token_is_invalid(): void
    {
        $route = new NotificationRouteVO(
            channelClass: FirebaseCloudMessagingChannel::class,
            destination: str_repeat('a', 200),
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test',
        );

        $result = $this->driver->send($message, $route);

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error_message);
        $this->assertStringContainsString(
            'Firebase trigger failed',
            $result->error_message->getValue(),
        );
    }
}
