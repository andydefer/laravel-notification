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
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test message'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test',
            data: new StrictDataObject(['extra_data' => 'value'])
        );

        $result = $this->driver->send($message, $this->route);

        $this->assertTrue($result->success);
        $this->assertEquals('database', $this->driver->getChannel());
    }

    public function test_get_channel_returns_database(): void
    {
        $this->assertEquals('database', $this->driver->getChannel());
    }

    public function test_validate_configuration_with_valid_table(): void
    {
        $this->assertTrue($this->driver->validateConfiguration());
    }

    public function test_validate_configuration_with_empty_table(): void
    {
        $config = new DatabaseConfigRecord(table: '');
        $driver = new DatabaseDriver($config);

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_send_returns_send_result_record(): void
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Hello world'),
            subject: new MessageSubjectVO('Greeting'),
            type: 'greeting'
        );

        $result = $this->driver->send($message, $this->route);

        $this->assertTrue($result->success);
        $this->assertEquals(DatabaseChannel::class, $result->channel->getValue());
        $this->assertEquals('database', $result->destination);
        $this->assertNull($result->error_message);
    }

    public function test_send_with_empty_configuration_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Driver AndyDefer\LaravelNotification\Drivers\DatabaseDriver configuration is invalid.');

        $config = new DatabaseConfigRecord(table: '');
        $driver = new DatabaseDriver($config);

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test'
        );

        $driver->send($message, $this->route);
    }

    public function test_send_with_metadata(): void
    {
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

        $result = $this->driver->send($message, $route);

        $this->assertTrue($result->success);
        $this->assertEquals('database', $result->destination);
        $this->assertEquals(DatabaseChannel::class, $result->channel->getValue());
    }

    public function test_send_without_metadata(): void
    {
        $route = new NotificationRouteVO(
            channelClass: DatabaseChannel::class,
            destination: 'database'
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test without metadata'),
            subject: new MessageSubjectVO('No Metadata'),
            type: 'simple'
        );

        $result = $this->driver->send($message, $route);

        $this->assertTrue($result->success);
        $this->assertNull($result->error_message);
    }
}
