<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Channels;

use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Drivers\FirebaseCloudMessagingDriver;
use AndyDefer\LaravelNotification\Tests\TestCase;

final class FirebaseChannelTest extends TestCase
{
    private FirebaseCloudMessagingChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange : Create the channel from the config
        $this->channel = new FirebaseCloudMessagingChannel(
            new NotificationConfig($this->app['config']),
        );
    }

    public function test_get_name_returns_firebase(): void
    {
        $this->assertEquals('firebase', $this->channel->getName());
    }

    public function test_get_label_returns_firebase_cloud_messaging(): void
    {
        $this->assertEquals('Firebase Cloud Messaging', $this->channel->getLabel());
    }

    public function test_get_icon_returns_fire(): void
    {
        $this->assertEquals('🔥', $this->channel->getIcon());
    }

    public function test_is_enabled_returns_true_when_enabled(): void
    {
        // Arrange : Enable the firebase channel in config
        $this->app['config']->set('notification.channels.firebase.enabled', true);

        // Act
        $isEnabled = $this->channel->isEnabled();

        // Assert
        $this->assertTrue($isEnabled);
    }

    public function test_is_enabled_returns_false_when_disabled(): void
    {
        // Arrange : Disable the firebase channel in config
        $this->app['config']->set('notification.channels.firebase.enabled', false);

        // Act
        $isEnabled = $this->channel->isEnabled();

        // Assert
        $this->assertFalse($isEnabled);
    }

    public function test_create_driver_returns_firebase_driver(): void
    {
        // Arrange : Ensure the config has valid firebase settings
        $this->app['config']->set('notification.channels.firebase', [
            'enabled' => true,
            'credentials_path' => $this->getEnv('FIREBASE_CREDENTIALS_PATH'),
            'project_id' => $this->getEnv('FIREBASE_PROJECT_ID'),
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'timeout' => (int) $this->getEnv('FIREBASE_TIMEOUT'),
        ]);

        // Act
        $driver = $this->channel->createDriver();

        // Assert
        $this->assertInstanceOf(FirebaseCloudMessagingDriver::class, $driver);
        $this->assertEquals('firebase', $driver->getChannel());
    }

    public function test_validate_destination_with_valid_token(): void
    {
        // Arrange & Act : Validate a realistic FCM token
        $isValid = FirebaseCloudMessagingChannel::validateDestination(
            'ejK3-OvYhYb7a_Ci5ZJimM:APA91bEm9P-qhuM2gd_G22QXC4l7bCvm6dXcJmPTXt5TKuIW7X6enUd3ec77OP0mtz8Ow5A28IVHBMqaLT4bx_NS-Nxn6avQM0scEcWohxkSCuMBkZOPgfA',
        );

        // Assert
        $this->assertTrue($isValid);
    }

    public function test_validate_destination_rejects_empty_string(): void
    {
        $this->assertFalse(FirebaseCloudMessagingChannel::validateDestination(''));
    }

    public function test_validate_destination_rejects_too_short(): void
    {
        $this->assertFalse(FirebaseCloudMessagingChannel::validateDestination(str_repeat('a', 99)));
    }

    public function test_validate_destination_rejects_too_long(): void
    {
        $this->assertFalse(FirebaseCloudMessagingChannel::validateDestination(str_repeat('a', 513)));
    }

    public function test_validate_destination_rejects_invalid_chars(): void
    {
        $this->assertFalse(FirebaseCloudMessagingChannel::validateDestination(
            str_repeat('a', 100).' with spaces',
        ));
    }

    public function test_validate_destination_accepts_max_length(): void
    {
        $this->assertTrue(FirebaseCloudMessagingChannel::validateDestination(str_repeat('a', 512)));
    }
}
