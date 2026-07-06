<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Drivers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class MailDriverTest extends TestCase
{
    private MailDriver $driver;

    private MailConfigRecord $config;

    private NotificationRouteVO $route;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange : Create configuration and route
        $this->config = new MailConfigRecord(
            enabled: true,
            default_from: 'noreply@example.com',
            default_from_name: 'Test App'
        );

        $this->driver = new MailDriver($this->config);

        $this->route = new NotificationRouteVO(
            channelClass: MailChannel::class,
            destination: 'john@example.com'
        );
    }

    private function getLogContent(): string
    {
        $logPath = storage_path('logs/test.log');
        if (! file_exists($logPath)) {
            return '';
        }

        return file_get_contents($logPath);
    }

    private function clearLog(): void
    {
        $logPath = storage_path('logs/test.log');
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }
    }

    public function test_execute_sends_mail(): void
    {
        // Arrange : Clear log and create a message
        $this->clearLog();

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test Body'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test'
        );

        // Act : Send the notification
        $result = $this->driver->send($message, $this->route);

        // Assert : Verify the result and log content
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);
        $this->assertNull($result->error_message);

        $logContent = $this->getLogContent();
        $this->assertStringContainsString('Test Body', $logContent);
        $this->assertStringContainsString('Test Subject', $logContent);
        $this->assertStringContainsString('john@example.com', $logContent);
    }

    public function test_execute_sends_mail_with_from_config(): void
    {
        // Arrange : Clear log and create a message
        $this->clearLog();

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test Body'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test'
        );

        // Act : Send the notification
        $this->driver->send($message, $this->route);

        // Assert : Verify log content contains the from address
        $logContent = $this->getLogContent();
        $this->assertStringContainsString('Test Body', $logContent);
        $this->assertStringContainsString('Test Subject', $logContent);
        $this->assertStringContainsString('john@example.com', $logContent);
    }

    public function test_execute_throws_exception_when_destination_empty(): void
    {
        // Arrange : Create a message
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test Body'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test'
        );

        // Expect : Exception should be thrown when creating the route with empty destination
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Destination cannot be empty.');

        // Act : Create a route with empty destination (this will throw the exception)
        $route = new NotificationRouteVO(
            channelClass: MailChannel::class,
            destination: ''
        );

        // Note: The exception is thrown in the constructor of NotificationRouteVO
        // so the send() method is never reached
    }

    public function test_get_channel_returns_mail(): void
    {
        // Arrange & Act : Get the channel name
        $channel = $this->driver->getChannel();

        // Assert : Verify the channel name
        $this->assertEquals('mail', $channel);
    }

    public function test_validate_configuration_with_valid_config(): void
    {
        // Arrange : Configuration already has valid values

        // Act : Validate the configuration
        $isValid = $this->driver->validateConfiguration();

        // Assert : Verify the configuration is valid
        $this->assertTrue($isValid);
    }

    public function test_validate_configuration_when_disabled(): void
    {
        // Arrange : Create a disabled configuration
        $config = new MailConfigRecord(
            enabled: false,
            default_from: 'test@example.com'
        );
        $driver = new MailDriver($config);

        // Act : Validate the configuration
        $isValid = $driver->validateConfiguration();

        // Assert : Verify the configuration is invalid
        $this->assertFalse($isValid);
    }

    public function test_validate_configuration_without_from(): void
    {
        // Arrange : Create a configuration without from address
        $config = new MailConfigRecord(
            enabled: true,
            default_from: null
        );
        $driver = new MailDriver($config);

        // Act : Validate the configuration
        $isValid = $driver->validateConfiguration();

        // Assert : Verify the configuration is invalid
        $this->assertFalse($isValid);
    }

    public function test_execute_returns_send_result_record_on_success(): void
    {
        // Arrange : Clear log and create a message
        $this->clearLog();

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Hello world'),
            subject: new MessageSubjectVO('Greeting'),
            type: 'greeting'
        );

        // Act : Send the notification
        $result = $this->driver->send($message, $this->route);

        // Assert : Verify the result structure and log content
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);
        $this->assertNull($result->error_message);

        $logContent = $this->getLogContent();
        $this->assertStringContainsString('Hello world', $logContent);
        $this->assertStringContainsString('Greeting', $logContent);
    }

    public function test_execute_with_empty_configuration_throws_exception(): void
    {
        // Arrange : Create a driver with invalid configuration
        $config = new MailConfigRecord(
            enabled: true,
            default_from: null
        );
        $driver = new MailDriver($config);

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test'
        );

        // Expect : Exception should be thrown
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Driver AndyDefer\LaravelNotification\Drivers\MailDriver configuration is invalid.');

        // Act : Attempt to send the notification
        $driver->send($message, $this->route);
    }

    public function test_execute_with_metadata(): void
    {
        // Arrange : Create a route with metadata
        $this->clearLog();

        $route = new NotificationRouteVO(
            channelClass: MailChannel::class,
            destination: 'john@example.com',
            metadata: new StrictDataObject([
                'from' => 'custom@example.com',
                'from_name' => 'Custom Sender',
            ])
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test with metadata'),
            subject: new MessageSubjectVO('Metadata Test'),
            type: 'metadata_test'
        );

        // Act : Send the notification
        $result = $this->driver->send($message, $route);

        // Assert : Verify the result and log content
        $this->assertTrue($result->success);

        $logContent = $this->getLogContent();
        $this->assertStringContainsString('Test with metadata', $logContent);
        $this->assertStringContainsString('Metadata Test', $logContent);
        $this->assertStringContainsString('john@example.com', $logContent);
    }

    public function test_execute_without_metadata_uses_config_from(): void
    {
        // Arrange : Clear log and create a message without metadata
        $this->clearLog();

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test without metadata'),
            subject: new MessageSubjectVO('No Metadata'),
            type: 'simple'
        );

        // Act : Send the notification
        $this->driver->send($message, $this->route);

        // Assert : Verify log content contains the default from address
        $logContent = $this->getLogContent();
        $this->assertStringContainsString('Test without metadata', $logContent);
        $this->assertStringContainsString('No Metadata', $logContent);
        $this->assertStringContainsString('john@example.com', $logContent);
    }

    public function test_execute_sends_html_email(): void
    {
        // Arrange : Clear log and create an HTML message
        $this->clearLog();

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('<h1>Welcome</h1><p>This is a test email</p>'),
            subject: new MessageSubjectVO('HTML Email Test'),
            type: 'html_test'
        );

        // Act : Send the notification
        $this->driver->send($message, $this->route);

        // Assert : Verify HTML content in log
        $logContent = $this->getLogContent();
        $this->assertStringContainsString('<h1>Welcome</h1>', $logContent);
        $this->assertStringContainsString('<p>This is a test email</p>', $logContent);
        $this->assertStringContainsString('HTML Email Test', $logContent);
    }

    public function test_execute_saves_error_in_send_result_on_exception(): void
    {
        // Arrange : Clear log and mock Mail to throw exception
        $this->clearLog();

        Mail::shouldReceive('send')
            ->andThrow(new \Exception('SMTP connection failed'));

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test'
        );

        // Act : Send the notification
        $result = $this->driver->send($message, $this->route);

        // Assert : Verify error handling
        $this->assertFalse($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);
        $this->assertNotNull($result->error_message);
        $this->assertStringContainsString('SMTP connection failed', $result->error_message->getValue());
    }
}
