<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Drivers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Drivers\PusherDriver;
use AndyDefer\LaravelNotification\Records\PusherConfigRecord;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class PusherDriverTest extends TestCase
{
    private PusherDriver $driver;

    private PusherConfigRecord $config;

    private NotificationRouteVO $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new PusherConfigRecord(
            enabled: true,
            app_id: (string) $this->getEnv('PUSHER_APP_ID'),
            key: (string) $this->getEnv('PUSHER_APP_KEY'),
            secret: (string) $this->getEnv('PUSHER_APP_SECRET'),
            cluster: (string) $this->getEnv('PUSHER_APP_CLUSTER'),
            use_tls: filter_var($this->getEnv('PUSHER_USE_TLS'), FILTER_VALIDATE_BOOLEAN),
            timeout: (int) $this->getEnv('PUSHER_TIMEOUT'),
            default_channel: (string) $this->getEnv('PUSHER_NOTIFICATION_CHANNEL'),
        );

        $this->driver = new PusherDriver($this->config);

        $this->route = new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: 'private-user.1',
        );
    }

    public function test_execute_triggers_pusher_event(): void
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test Body'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test',
        );

        $result = $this->driver->send($message, $this->route);

        $this->assertTrue($result->success);
        $this->assertEquals(PusherChannel::class, $result->channel->getValue());
        $this->assertEquals('private-user.1', $result->destination);
        $this->assertNull($result->error_message);
    }

    public function test_execute_with_metadata_overrides_channel_and_event(): void
    {
        $route = new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: 'private-user.1',
            metadata: new StrictDataObject([
                'channel' => 'private-tenant.42',
                'event' => 'notification.custom',
            ]),
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test with metadata'),
            subject: new MessageSubjectVO('Metadata Test'),
            type: 'metadata_test',
        );

        $result = $this->driver->send($message, $route);

        $this->assertTrue($result->success);
        $this->assertEquals('private-user.1', $result->destination);
        $this->assertNull($result->error_message);
    }

    public function test_execute_uses_default_channel_from_config(): void
    {
        $route = new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: (string) $this->getEnv('PUSHER_NOTIFICATION_CHANNEL'),
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test',
        );

        $result = $this->driver->send($message, $route);

        $this->assertTrue($result->success);
        $this->assertEquals((string) $this->getEnv('PUSHER_NOTIFICATION_CHANNEL'), $result->destination);
        $this->assertNull($result->error_message);
    }

    public function test_execute_with_data_includes_data_payload(): void
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test Body'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'order_confirmation',
            data: new StrictDataObject([
                'order_id' => 42,
                'customer_name' => 'John Doe',
            ]),
        );

        $result = $this->driver->send($message, $this->route);

        $this->assertTrue($result->success);
        $this->assertEquals(PusherChannel::class, $result->channel->getValue());
        $this->assertNull($result->error_message);
    }

    public function test_get_channel_returns_pusher(): void
    {
        $this->assertEquals('pusher', $this->driver->getChannel());
    }

    public function test_validate_configuration_with_valid_config(): void
    {
        $this->assertTrue($this->driver->validateConfiguration());
    }

    public function test_execute_with_empty_configuration_throws_exception(): void
    {
        $config = new PusherConfigRecord(
            enabled: true,
            app_id: null,
            key: null,
            secret: null,
            cluster: 'ap2',
        );
        $driver = new PusherDriver($config);

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Driver AndyDefer\LaravelNotification\Drivers\PusherDriver configuration is invalid.',
        );

        $driver->send($message, $this->route);
    }

    public function test_execute_saves_error_in_send_result_on_exception(): void
    {
        $config = new PusherConfigRecord(
            enabled: true,
            app_id: (string) $this->getEnv('PUSHER_APP_ID'),
            key: (string) $this->getEnv('PUSHER_APP_KEY'),
            secret: 'invalid-secret',
            cluster: (string) $this->getEnv('PUSHER_APP_CLUSTER'),
            use_tls: true,
            timeout: (int) $this->getEnv('PUSHER_TIMEOUT'),
            default_channel: (string) $this->getEnv('PUSHER_NOTIFICATION_CHANNEL'),
        );
        $driver = new PusherDriver($config);

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test',
        );

        $result = $driver->send($message, $this->route);

        $this->assertFalse($result->success);
        $this->assertEquals(PusherChannel::class, $result->channel->getValue());
        $this->assertEquals('private-user.1', $result->destination);
        $this->assertNotNull($result->error_message);
        $this->assertStringContainsString('Pusher trigger failed', $result->error_message->getValue());
    }
}
