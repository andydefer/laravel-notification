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
        $this->clearLog();

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test Body'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test'
        );

        $result = $this->driver->send($message, $this->route);

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
        $this->clearLog();

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test Body'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test'
        );

        $this->driver->send($message, $this->route);

        $logContent = $this->getLogContent();
        $this->assertStringContainsString('Test Body', $logContent);
        $this->assertStringContainsString('Test Subject', $logContent);
        $this->assertStringContainsString('john@example.com', $logContent);
    }

    public function test_execute_throws_exception_when_destination_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Destination cannot be empty.');

        $route = new NotificationRouteVO(
            channelClass: MailChannel::class,
            destination: ''
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test Body'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test'
        );

        $this->driver->send($message, $route);
    }

    public function test_get_channel_returns_mail(): void
    {
        $this->assertEquals('mail', $this->driver->getChannel());
    }

    public function test_validate_configuration_with_valid_config(): void
    {
        $this->assertTrue($this->driver->validateConfiguration());
    }

    public function test_validate_configuration_when_disabled(): void
    {
        $config = new MailConfigRecord(
            enabled: false,
            default_from: 'test@example.com'
        );
        $driver = new MailDriver($config);

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_validate_configuration_without_from(): void
    {
        $config = new MailConfigRecord(
            enabled: true,
            default_from: null
        );
        $driver = new MailDriver($config);

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_execute_returns_send_result_record_on_success(): void
    {
        $this->clearLog();

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Hello world'),
            subject: new MessageSubjectVO('Greeting'),
            type: 'greeting'
        );

        $result = $this->driver->send($message, $this->route);

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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Driver AndyDefer\LaravelNotification\Drivers\MailDriver configuration is invalid.');

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

        $driver->send($message, $this->route);
    }

    public function test_execute_with_metadata(): void
    {
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

        $result = $this->driver->send($message, $route);

        $this->assertTrue($result->success);

        $logContent = $this->getLogContent();
        $this->assertStringContainsString('Test with metadata', $logContent);
        $this->assertStringContainsString('Metadata Test', $logContent);
        $this->assertStringContainsString('john@example.com', $logContent);
    }

    public function test_execute_without_metadata_uses_config_from(): void
    {
        $this->clearLog();

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test without metadata'),
            subject: new MessageSubjectVO('No Metadata'),
            type: 'simple'
        );

        $this->driver->send($message, $this->route);

        $logContent = $this->getLogContent();
        $this->assertStringContainsString('Test without metadata', $logContent);
        $this->assertStringContainsString('No Metadata', $logContent);
        $this->assertStringContainsString('john@example.com', $logContent);
    }

    public function test_execute_sends_html_email(): void
    {
        $this->clearLog();

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('<h1>Welcome</h1><p>This is a test email</p>'),
            subject: new MessageSubjectVO('HTML Email Test'),
            type: 'html_test'
        );

        $this->driver->send($message, $this->route);

        $logContent = $this->getLogContent();
        $this->assertStringContainsString('<h1>Welcome</h1>', $logContent);
        $this->assertStringContainsString('<p>This is a test email</p>', $logContent);
        $this->assertStringContainsString('HTML Email Test', $logContent);
    }

    public function test_execute_saves_error_in_send_result_on_exception(): void
    {
        $this->clearLog();

        Mail::shouldReceive('send')
            ->andThrow(new \Exception('SMTP connection failed'));

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test'
        );

        $result = $this->driver->send($message, $this->route);

        $this->assertFalse($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);
        $this->assertNotNull($result->error_message);
        $this->assertStringContainsString('SMTP connection failed', $result->error_message->getValue());
    }
}
