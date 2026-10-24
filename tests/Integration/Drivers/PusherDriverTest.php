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

        // ⬇️ La route ne porte QUE le channel (destination)
        $this->route = new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: 'private-user.1',
        );
    }

    private function makeMessage(
        string $body = 'Test Body',
        string $subject = 'Test Subject',
        string $type = 'test',
        ?StrictDataObject $data = null,
        ?StrictDataObject $metadata = null,
    ): NotificationMessageVO {
        return new NotificationMessageVO(
            body: new MessageBodyVO($body),
            subject: new MessageSubjectVO($subject),
            type: $type,
            data: $data,
            metadata: $metadata,
        );
    }

    // ============================================================
    // Channel + Validation
    // ============================================================

    public function test_get_channel_returns_pusher(): void
    {
        $this->assertSame('pusher', $this->driver->getChannel());
    }

    public function test_validate_configuration_with_valid_config(): void
    {
        $this->assertTrue($this->driver->validateConfiguration());
    }

    public function test_execute_with_empty_configuration_throws_exception(): void
    {
        $driver = new PusherDriver(new PusherConfigRecord(
            enabled: true,
            app_id: null,
            key: null,
            secret: null,
            cluster: 'ap2',
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Driver AndyDefer\LaravelNotification\Drivers\PusherDriver configuration is invalid.',
        );

        $driver->send($this->makeMessage(), $this->route);
    }

    // ============================================================
    // Basic send
    // ============================================================

    public function test_execute_triggers_pusher_event(): void
    {
        $result = $this->driver->send($this->makeMessage(), $this->route);

        $this->assertTrue($result->success);
        $this->assertEquals(PusherChannel::class, $result->channel->getValue());
        $this->assertEquals('private-user.1', $result->destination);
        $this->assertNull($result->error_message);
    }

    public function test_execute_with_data_includes_data_payload(): void
    {
        $result = $this->driver->send(
            $this->makeMessage(
                type: 'order_confirmation',
                data: new StrictDataObject([
                    'order_id' => 42,
                    'customer_name' => 'John Doe',
                ]),
            ),
            $this->route,
        );

        $this->assertTrue($result->success);
    }

    // ============================================================
    // NEW — metadata: event (depuis le message)
    // ============================================================

    public function test_execute_with_custom_event_from_message_metadata(): void
    {
        $result = $this->driver->send(
            $this->makeMessage(
                metadata: new StrictDataObject([
                    'event' => 'notification.custom',
                ]),
            ),
            $this->route,
        );

        $this->assertTrue($result->success);
        $this->assertNull($result->error_message);
    }

    public function test_execute_falls_back_to_default_event_when_metadata_absent(): void
    {
        $result = $this->driver->send(
            $this->makeMessage(), // pas de metadata
            $this->route,
        );

        // Doit réussir avec DEFAULT_EVENT = 'notification'
        $this->assertTrue($result->success);
    }

    public function test_execute_falls_back_to_default_event_when_metadata_event_empty(): void
    {
        $result = $this->driver->send(
            $this->makeMessage(
                metadata: new StrictDataObject([
                    'event' => '',  // vide → fallback
                ]),
            ),
            $this->route,
        );

        $this->assertTrue($result->success);
    }

    public function test_execute_falls_back_to_default_event_when_metadata_event_not_string(): void
    {
        $result = $this->driver->send(
            $this->makeMessage(
                metadata: new StrictDataObject([
                    'event' => 42,  // non-string → fallback
                ]),
            ),
            $this->route,
        );

        $this->assertTrue($result->success);
    }

    // ============================================================
    // NEW — channel resolution priority
    // ============================================================

    public function test_execute_uses_destination_as_channel(): void
    {
        $result = $this->driver->send($this->makeMessage(), $this->route);

        $this->assertTrue($result->success);
        $this->assertEquals('private-user.1', $result->destination);
    }

    public function test_execute_with_metadata_channel_override(): void
    {
        // ⬇️ metadata.channel doit PRENDRE LE DESSUS sur destination
        //    (via la route)
        $route = new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: 'private-user.1',
            metadata: new StrictDataObject([
                'channel' => 'private-tenant.42',
            ]),
        );

        $result = $this->driver->send($this->makeMessage(), $route);

        $this->assertTrue($result->success);
        // destination reste la valeur d'origine (le résultat reflète la route)
        $this->assertEquals('private-user.1', $result->destination);
    }

    public function test_execute_with_default_channel_from_config(): void
    {
        $route = new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: (string) $this->getEnv('PUSHER_NOTIFICATION_CHANNEL'),
        );

        $result = $this->driver->send($this->makeMessage(), $route);

        $this->assertTrue($result->success);
        $this->assertEquals(
            (string) $this->getEnv('PUSHER_NOTIFICATION_CHANNEL'),
            $result->destination,
        );
    }

    // ============================================================
    // Announcements (canal public)
    // ============================================================

    public function test_execute_triggers_public_announcements_channel(): void
    {
        $route = new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: 'announcements',
        );

        $result = $this->driver->send(
            $this->makeMessage(
                subject: 'Hello everyone',
                type: 'announcement',
                data: new StrictDataObject(['priority' => 'high']),
            ),
            $route,
        );

        $this->assertTrue($result->success);
        $this->assertEquals('announcements', $result->destination);
    }

    public function test_execute_triggers_public_announcements_with_custom_event(): void
    {
        $route = new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: 'announcements',
        );

        $result = $this->driver->send(
            $this->makeMessage(
                type: 'announcement',
                metadata: new StrictDataObject([
                    'event' => 'notification.custom',
                ]),
            ),
            $route,
        );

        $this->assertTrue($result->success);
        $this->assertEquals('announcements', $result->destination);
    }

    // ============================================================
    // Error handling
    // ============================================================

    public function test_execute_saves_error_in_send_result_on_exception(): void
    {
        $driver = new PusherDriver(new PusherConfigRecord(
            enabled: true,
            app_id: (string) $this->getEnv('PUSHER_APP_ID'),
            key: (string) $this->getEnv('PUSHER_APP_KEY'),
            secret: 'invalid-secret',
            cluster: (string) $this->getEnv('PUSHER_APP_CLUSTER'),
            use_tls: true,
            timeout: (int) $this->getEnv('PUSHER_TIMEOUT'),
            default_channel: (string) $this->getEnv('PUSHER_NOTIFICATION_CHANNEL'),
        ));

        $result = $driver->send($this->makeMessage(), $this->route);

        $this->assertFalse($result->success);
        $this->assertEquals(PusherChannel::class, $result->channel->getValue());
        $this->assertEquals('private-user.1', $result->destination);
        $this->assertNotNull($result->error_message);
        $this->assertStringContainsString(
            'Pusher trigger failed',
            $result->error_message->getValue(),
        );
    }
}
