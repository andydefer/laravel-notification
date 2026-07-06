<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Drivers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Drivers\DatabaseDriver;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class DatabaseDriverTest extends TestCase
{
    private DatabaseDriver $driver;

    private DatabaseConfigRecord $config;

    private NotificationRouteVO $route;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange : Create configuration and route
        $this->config = new DatabaseConfigRecord(
            table: 'notifications'
        );

        $this->driver = new DatabaseDriver($this->config);

        $this->route = new NotificationRouteVO(
            channelClass: DatabaseChannel::class,
            destination: 'database',
            metadata: new StrictDataObject(['type' => 'database'])
        );
    }

    public function test_execute_returns_true(): void
    {
        // Arrange : Create a notification message
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test message'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test',
            data: new StrictDataObject(['extra_data' => 'value'])
        );

        // Act : Send the notification
        $result = $this->driver->send($message, $this->route);

        // Assert : Verify the result
        $this->assertTrue($result->success);
        $this->assertEquals('database', $this->driver->getChannel());
    }

    public function test_get_channel_returns_database(): void
    {
        // Arrange & Act : Get the channel name
        $channel = $this->driver->getChannel();

        // Assert : Verify the channel name
        $this->assertEquals('database', $channel);
    }

    public function test_validate_configuration_with_valid_table(): void
    {
        // Arrange : Configuration already has a valid table

        // Act : Validate the configuration
        $isValid = $this->driver->validateConfiguration();

        // Assert : Verify the configuration is valid
        $this->assertTrue($isValid);
    }

    public function test_validate_configuration_with_empty_table(): void
    {
        // Arrange : Create a driver with empty table
        $config = new DatabaseConfigRecord(table: '');
        $driver = new DatabaseDriver($config);

        // Act : Validate the configuration
        $isValid = $driver->validateConfiguration();

        // Assert : Verify the configuration is invalid
        $this->assertFalse($isValid);
    }

    public function test_send_returns_send_result_record(): void
    {
        // Arrange : Create a notification message
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Hello world'),
            subject: new MessageSubjectVO('Greeting'),
            type: 'greeting'
        );

        // Act : Send the notification
        $result = $this->driver->send($message, $this->route);

        // Assert : Verify the result structure
        $this->assertTrue($result->success);
        $this->assertEquals(DatabaseChannel::class, $result->channel->getValue());
        $this->assertEquals('database', $result->destination);
        $this->assertNull($result->error_message);
    }

    public function test_send_with_empty_configuration_throws_exception(): void
    {
        // Arrange : Create a driver with empty table
        $config = new DatabaseConfigRecord(table: '');
        $driver = new DatabaseDriver($config);

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test'
        );

        // Expect : Exception should be thrown
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Driver AndyDefer\LaravelNotification\Drivers\DatabaseDriver configuration is invalid.');

        // Act : Attempt to send the notification
        $driver->send($message, $this->route);
    }

    public function test_send_with_metadata(): void
    {
        // Arrange : Create a route with metadata
        $route = new NotificationRouteVO(
            channelClass: DatabaseChannel::class,
            destination: 'database',
            metadata: new StrictDataObject([
                'type' => 'database',
                'priority' => 'high',
                'tags' => ['important', 'system'],
            ])
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test with metadata'),
            subject: new MessageSubjectVO('Metadata Test'),
            type: 'metadata_test'
        );

        // Act : Send the notification
        $result = $this->driver->send($message, $route);

        // Assert : Verify the result
        $this->assertTrue($result->success);
        $this->assertEquals('database', $result->destination);
        $this->assertEquals(DatabaseChannel::class, $result->channel->getValue());
    }

    public function test_send_without_metadata(): void
    {
        // Arrange : Create a route without metadata
        $route = new NotificationRouteVO(
            channelClass: DatabaseChannel::class,
            destination: 'database'
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test without metadata'),
            subject: new MessageSubjectVO('No Metadata'),
            type: 'simple'
        );

        // Act : Send the notification
        $result = $this->driver->send($message, $route);

        // Assert : Verify the result
        $this->assertTrue($result->success);
        $this->assertNull($result->error_message);
    }
}
