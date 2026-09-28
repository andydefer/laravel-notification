<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Channels;

use AndyDefer\LaravelNotification\Channels\WebPushChannel;
use AndyDefer\LaravelNotification\Contracts\Configs\NotificationConfigInterface;
use AndyDefer\LaravelNotification\Drivers\WebPushDriver;
use AndyDefer\LaravelNotification\Records\WebPushConfigRecord;
use AndyDefer\LaravelNotification\Tests\TestCase;

final class WebPushChannelTest extends TestCase
{
    public function test_get_name_returns_webpush(): void
    {
        $channel = $this->app->make(WebPushChannel::class);

        $this->assertSame('webpush', $channel->getName());
    }

    public function test_get_label_returns_expected_label(): void
    {
        $channel = $this->app->make(WebPushChannel::class);

        $this->assertSame('Web Push (VAPID)', $channel->getLabel());
    }

    public function test_get_icon_returns_globe(): void
    {
        $channel = $this->app->make(WebPushChannel::class);

        $this->assertSame('🌐', $channel->getIcon());
    }

    public function test_is_enabled_when_config_enabled(): void
    {
        $config = $this->app->make(NotificationConfigInterface::class);

        $this->assertTrue($config->isWebPushEnabled());

        $channel = $this->app->make(WebPushChannel::class);

        $this->assertTrue($channel->isEnabled());
    }

    public function test_is_disabled_when_config_disabled(): void
    {
        $this->app['config']->set('notification.channels.webpush.enabled', false);

        $this->app->forgetInstance(NotificationConfigInterface::class);

        $channel = $this->app->make(WebPushChannel::class);

        $this->assertFalse($channel->isEnabled());
    }

    public function test_get_config_returns_webpush_config_record(): void
    {
        $channel = $this->app->make(WebPushChannel::class);

        $config = $channel->getConfig();

        $this->assertInstanceOf(WebPushConfigRecord::class, $config);
        $this->assertSame('BDw92Y6vnPXYNN90QwiqmLAnnEX9GJZo27by3Bpo2nATkEAivqJIf436LV_Vc8Hg7S2fkzJp3Ow_5p0CVCVTX5g', $config->public_key);
        $this->assertSame('m7lTHzndL5P7QL0be8a0l1whYsy8pFhKJD6dYtRvUpA', $config->private_key);
        $this->assertSame('mailto:contact@afya-medical.com', $config->subject);
        $this->assertSame(3600, $config->ttl);
        $this->assertSame('normal', $config->urgency);
        $this->assertSame('notification', $config->topic);
    }

    public function test_create_driver_returns_webpush_driver(): void
    {
        $channel = $this->app->make(WebPushChannel::class);

        $driver = $channel->createDriver();

        $this->assertInstanceOf(WebPushDriver::class, $driver);
        $this->assertSame('webpush', $driver->getChannel());
    }

    public function test_validate_destination_accepts_https_url(): void
    {
        $this->assertTrue(WebPushChannel::validateDestination('https://fcm.googleapis.com/fcm/send/abc'));
    }

    public function test_validate_destination_accepts_http_url(): void
    {
        $this->assertTrue(WebPushChannel::validateDestination('http://localhost/push/abc'));
    }

    public function test_validate_destination_rejects_empty_string(): void
    {
        $this->assertFalse(WebPushChannel::validateDestination(''));
    }

    public function test_validate_destination_rejects_random_text(): void
    {
        $this->assertFalse(WebPushChannel::validateDestination('not-a-url'));
    }

    public function test_validate_destination_rejects_ftp_url(): void
    {
        $this->assertFalse(WebPushChannel::validateDestination('ftp://example.com/path'));
    }
}
