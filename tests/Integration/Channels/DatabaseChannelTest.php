<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Channels;

use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Drivers\DatabaseDriver;
use AndyDefer\LaravelNotification\Tests\TestCase;

final class DatabaseChannelTest extends TestCase
{
    private DatabaseChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange : Create the channel from the config
        $this->channel = new DatabaseChannel(
            new NotificationConfig($this->app['config']),
        );
    }

    public function test_get_name_returns_database(): void
    {
        // Arrange & Act : Get the channel name
        $name = $this->channel->getName();

        // Assert : Verify the channel name
        $this->assertEquals('database', $name);
    }

    public function test_get_label_returns_french_label(): void
    {
        // Arrange & Act : Get the channel label
        $label = $this->channel->getLabel();

        // Assert : Verify the channel label
        $this->assertEquals('Base de données', $label);
    }

    public function test_get_icon_returns_floppy_disk(): void
    {
        // Arrange & Act : Get the channel icon
        $icon = $this->channel->getIcon();

        // Assert : Verify the channel icon
        $this->assertEquals('💾', $icon);
    }

    public function test_is_enabled_always_returns_true(): void
    {
        // Arrange : Disable everything else to prove database stays on
        $this->app['config']->set('notification.channels.mail.enabled', false);
        $this->app['config']->set('notification.channels.sms.enabled', false);

        // Act : Check if the channel is enabled
        $isEnabled = $this->channel->isEnabled();

        // Assert : Verify the channel is always enabled
        $this->assertTrue($isEnabled);
    }

    public function test_create_driver_returns_database_driver(): void
    {
        // Arrange : Ensure the config has valid database settings
        $this->app['config']->set('notification.channels.database', [
            'driver' => 'database',
            'table' => 'notifications',
        ]);

        // Act : Create the driver
        $driver = $this->channel->createDriver();

        // Assert : Verify the driver is a DatabaseDriver
        $this->assertInstanceOf(DatabaseDriver::class, $driver);
        $this->assertEquals('database', $driver->getChannel());
    }

    public function test_validate_destination_accepts_database_keyword(): void
    {
        // Arrange & Act : Validate the canonical destination keyword
        $isValid = DatabaseChannel::validateDestination('database');

        // Assert : Verify the destination is valid
        $this->assertTrue($isValid);
    }

    public function test_validate_destination_rejects_empty_string(): void
    {
        // Arrange & Act : Validate an empty destination
        $isValid = DatabaseChannel::validateDestination('');

        // Assert : Verify the destination is invalid
        $this->assertFalse($isValid);
    }

    public function test_validate_destination_rejects_arbitrary_string(): void
    {
        // Arrange & Act : Validate an arbitrary string
        $isValid = DatabaseChannel::validateDestination('some-table');

        // Assert : Verify the destination is invalid
        $this->assertFalse($isValid);
    }
}
