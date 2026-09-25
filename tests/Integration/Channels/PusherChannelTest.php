<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Channels;

use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Drivers\PusherDriver;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class PusherChannelTest extends TestCase
{
    private PusherChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = new PusherChannel(
            new NotificationConfig($this->app['config']),
        );
    }

    public function test_get_name_returns_pusher(): void
    {
        $this->assertEquals('pusher', $this->channel->getName());
    }

    public function test_get_label_returns_pusher(): void
    {
        $this->assertEquals('Pusher', $this->channel->getLabel());
    }

    public function test_get_icon_returns_radio(): void
    {
        $this->assertEquals('radio', $this->channel->getIcon());
    }

    public function test_is_enabled_returns_true_when_enabled(): void
    {
        $this->app['config']->set('notification.channels.pusher.enabled', true);

        $this->assertTrue($this->channel->isEnabled());
    }

    public function test_is_enabled_returns_false_when_disabled(): void
    {
        $this->app['config']->set('notification.channels.pusher.enabled', false);

        $this->assertFalse($this->channel->isEnabled());
    }

    public function test_create_driver_returns_pusher_driver(): void
    {
        $this->app['config']->set('notification.channels.pusher', [
            'enabled' => true,
            'app_id' => $this->getEnv('PUSHER_APP_ID'),
            'key' => $this->getEnv('PUSHER_APP_KEY'),
            'secret' => $this->getEnv('PUSHER_APP_SECRET'),
            'cluster' => $this->getEnv('PUSHER_APP_CLUSTER'),
            'use_tls' => filter_var($this->getEnv('PUSHER_USE_TLS'), FILTER_VALIDATE_BOOLEAN),
            'timeout' => (int) $this->getEnv('PUSHER_TIMEOUT'),
            'default_channel' => $this->getEnv('PUSHER_NOTIFICATION_CHANNEL'),
        ]);

        $driver = $this->channel->createDriver();

        $this->assertInstanceOf(PusherDriver::class, $driver);
        $this->assertEquals('pusher', $driver->getChannel());
    }

    public function test_create_driver_and_send_notification(): void
    {
        $this->app['config']->set('notification.channels.pusher', [
            'enabled' => true,
            'app_id' => $this->getEnv('PUSHER_APP_ID'),
            'key' => $this->getEnv('PUSHER_APP_KEY'),
            'secret' => $this->getEnv('PUSHER_APP_SECRET'),
            'cluster' => $this->getEnv('PUSHER_APP_CLUSTER'),
            'use_tls' => filter_var($this->getEnv('PUSHER_USE_TLS'), FILTER_VALIDATE_BOOLEAN),
            'timeout' => (int) $this->getEnv('PUSHER_TIMEOUT'),
            'default_channel' => $this->getEnv('PUSHER_NOTIFICATION_CHANNEL'),
        ]);

        $driver = $this->channel->createDriver();

        $route = new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: 'private-user.1',
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Channel body'),
            subject: new MessageSubjectVO('Channel subject'),
            type: 'channel_test',
        );

        $result = $driver->send($message, $route);

        $this->assertTrue($result->success);
        $this->assertEquals(PusherChannel::class, $result->channel->getValue());
        $this->assertEquals('private-user.1', $result->destination);
        $this->assertNull($result->error_message);
    }

    public function test_validate_destination_with_valid_channel_name(): void
    {
        $this->assertTrue(PusherChannel::validateDestination('private-user.1'));
    }

    public function test_validate_destination_with_valid_public_channel(): void
    {
        $this->assertTrue(PusherChannel::validateDestination('notifications'));
    }

    public function test_validate_destination_with_special_chars(): void
    {
        $this->assertTrue(PusherChannel::validateDestination('user_1=active@prod,42;semi'));
    }

    public function test_validate_destination_rejects_empty_string(): void
    {
        $this->assertFalse(PusherChannel::validateDestination(''));
    }

    public function test_validate_destination_rejects_invalid_chars(): void
    {
        $this->assertFalse(PusherChannel::validateDestination('invalid channel!'));
    }

    public function test_validate_destination_rejects_too_long_name(): void
    {
        $this->assertFalse(PusherChannel::validateDestination(str_repeat('a', 165)));
    }

    public function test_validate_destination_accepts_max_length_name(): void
    {
        $this->assertTrue(PusherChannel::validateDestination(str_repeat('a', 164)));
    }
}
