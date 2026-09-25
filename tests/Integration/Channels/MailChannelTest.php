<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Channels;

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Tests\TestCase;

final class MailChannelTest extends TestCase
{
    private MailChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange : Create the channel from the config
        $this->channel = new MailChannel(
            new NotificationConfig($this->app['config']),
        );
    }

    public function test_get_name_returns_mail(): void
    {
        // Arrange & Act : Get the channel name
        $name = $this->channel->getName();

        // Assert : Verify the channel name
        $this->assertEquals('mail', $name);
    }

    public function test_get_label_returns_email(): void
    {
        // Arrange & Act : Get the channel label
        $label = $this->channel->getLabel();

        // Assert : Verify the channel label
        $this->assertEquals('Email', $label);
    }

    public function test_get_icon_returns_envelope(): void
    {
        // Arrange & Act : Get the channel icon
        $icon = $this->channel->getIcon();

        // Assert : Verify the channel icon
        $this->assertEquals('📧', $icon);
    }

    public function test_is_enabled_returns_true_when_enabled(): void
    {
        // Arrange : Enable the mail channel in config
        $this->app['config']->set('notification.channels.mail.enabled', true);

        // Act : Check if the channel is enabled
        $isEnabled = $this->channel->isEnabled();

        // Assert : Verify the channel is enabled
        $this->assertTrue($isEnabled);
    }

    public function test_is_enabled_returns_false_when_disabled(): void
    {
        // Arrange : Disable the mail channel in config
        $this->app['config']->set('notification.channels.mail.enabled', false);

        // Act : Check if the channel is enabled
        $isEnabled = $this->channel->isEnabled();

        // Assert : Verify the channel is disabled
        $this->assertFalse($isEnabled);
    }

    public function test_create_driver_returns_mail_driver(): void
    {
        // Arrange : Ensure the config has valid mail settings
        $this->app['config']->set('notification.channels.mail', [
            'enabled' => true,
            'driver' => 'mail',
            'default_from' => $this->getEnv('MAIL_FROM_ADDRESS'),
            'default_from_name' => $this->getEnv('MAIL_FROM_NAME'),
            'default_to' => $this->getEnv('MAIL_DEFAULT_TO'),
        ]);

        // Act : Create the driver
        $driver = $this->channel->createDriver();

        // Assert : Verify the driver is a MailDriver
        $this->assertInstanceOf(MailDriver::class, $driver);
        $this->assertEquals('mail', $driver->getChannel());
    }

    public function test_validate_destination_with_valid_email(): void
    {
        // Arrange & Act : Validate a well-formed email address
        $isValid = MailChannel::validateDestination('john@example.com');

        // Assert : Verify the destination is valid
        $this->assertTrue($isValid);
    }

    public function test_validate_destination_with_valid_email_and_plus(): void
    {
        // Arrange & Act : Validate an email with a plus tag
        $isValid = MailChannel::validateDestination('john+tag@example.com');

        // Assert : Verify the destination is valid
        $this->assertTrue($isValid);
    }

    public function test_validate_destination_rejects_empty_string(): void
    {
        // Arrange & Act : Validate an empty destination
        $isValid = MailChannel::validateDestination('');

        // Assert : Verify the destination is invalid
        $this->assertFalse($isValid);
    }

    public function test_validate_destination_rejects_missing_at(): void
    {
        // Arrange & Act : Validate an email without "@"
        $isValid = MailChannel::validateDestination('johnexample.com');

        // Assert : Verify the destination is invalid
        $this->assertFalse($isValid);
    }

    public function test_validate_destination_rejects_missing_domain(): void
    {
        // Arrange & Act : Validate an email without a domain
        $isValid = MailChannel::validateDestination('john@');

        // Assert : Verify the destination is invalid
        $this->assertFalse($isValid);
    }

    public function test_validate_destination_rejects_spaces(): void
    {
        // Arrange & Act : Validate an email with spaces
        $isValid = MailChannel::validateDestination('john doe@example.com');

        // Assert : Verify the destination is invalid
        $this->assertFalse($isValid);
    }
}
