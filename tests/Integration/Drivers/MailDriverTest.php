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
use RuntimeException;

final class MailDriverTest extends TestCase
{
    private MailDriver $driver;

    private MailConfigRecord $config;

    private NotificationRouteVO $route;

    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new MailConfigRecord(
            enabled: true,
            default_from: 'noreply@example.com',
            default_from_name: 'Test App',
        );

        $this->driver = new MailDriver($this->config);

        $this->route = new NotificationRouteVO(
            channelClass: MailChannel::class,
            destination: 'john@example.com',
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function createTempFile(string $content = 'Test content', string $ext = 'pdf'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mail_test_').'.'.$ext;

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Impossible de créer le fichier temporaire : {$path}");
        }

        $this->tempFiles[] = $path;

        return $path;
    }

    private function getLogContent(): string
    {
        $logPath = storage_path('logs/test.log');

        return file_exists($logPath) ? (string) file_get_contents($logPath) : '';
    }

    private function clearLog(): void
    {
        $logPath = storage_path('logs/test.log');
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
        }
    }

    private function makeMessage(
        string $body = 'Test Body',
        string $subject = 'Test Subject',
        string $type = 'test',
        ?StrictDataObject $metadata = null,
    ): NotificationMessageVO {
        return new NotificationMessageVO(
            body: new MessageBodyVO($body),
            subject: new MessageSubjectVO($subject),
            type: $type,
            metadata: $metadata,
        );
    }

    // ============================================================
    // Channel + Validation
    // ============================================================

    public function test_get_channel_returns_mail(): void
    {
        $this->assertSame('mail', $this->driver->getChannel());
    }

    public function test_validate_configuration_with_valid_config(): void
    {
        $this->assertTrue($this->driver->validateConfiguration());
    }

    public function test_validate_configuration_when_disabled(): void
    {
        $driver = new MailDriver(new MailConfigRecord(
            enabled: false,
            default_from: 'test@example.com',
        ));

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_validate_configuration_without_from(): void
    {
        $driver = new MailDriver(new MailConfigRecord(
            enabled: true,
            default_from: null,
        ));

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_execute_with_empty_configuration_throws_exception(): void
    {
        $driver = new MailDriver(new MailConfigRecord(
            enabled: true,
            default_from: null,
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Driver AndyDefer\LaravelNotification\Drivers\MailDriver configuration is invalid.',
        );

        $driver->send($this->makeMessage(), $this->route);
    }

    // ============================================================
    // Basic send
    // ============================================================

    public function test_execute_sends_mail(): void
    {
        $this->clearLog();

        $result = $this->driver->send($this->makeMessage(), $this->route);

        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);
        $this->assertNull($result->error_message);

        $log = $this->getLogContent();
        $this->assertStringContainsString('Test Body', $log);
        $this->assertStringContainsString('Test Subject', $log);
        $this->assertStringContainsString('john@example.com', $log);
    }

    public function test_execute_sends_html_email(): void
    {
        $this->clearLog();

        $this->driver->send(
            $this->makeMessage(
                body: '<h1>Welcome</h1><p>Test</p>',
                subject: 'HTML Test',
            ),
            $this->route,
        );

        $log = $this->getLogContent();
        $this->assertStringContainsString('<h1>Welcome</h1>', $log);
        $this->assertStringContainsString('HTML Test', $log);
    }

    public function test_execute_without_metadata_uses_config_from(): void
    {
        $this->clearLog();

        $this->driver->send($this->makeMessage(), $this->route);

        $log = $this->getLogContent();
        $this->assertStringContainsString('noreply@example.com', $log);
    }

    // ============================================================
    // NEW — metadata: attachments
    // ============================================================

    public function test_execute_with_single_string_attachment(): void
    {
        $this->clearLog();

        $file = $this->createTempFile('PDF content for single attachment');

        $metadata = new StrictDataObject([
            'attachments' => $file,
        ]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success, sprintf(
            'Mail send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertNull($result->error_message);
    }

    public function test_execute_with_array_of_string_attachments(): void
    {
        $this->clearLog();

        $file1 = $this->createTempFile('First PDF content');
        $file2 = $this->createTempFile('Second PDF content');

        $metadata = new StrictDataObject([
            'attachments' => [$file1, $file2],
        ]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success, sprintf(
            'Mail send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertNull($result->error_message);
    }

    public function test_execute_with_attachment_objects(): void
    {
        $this->clearLog();

        $file = $this->createTempFile('PDF content for object attachment');

        $metadata = new StrictDataObject([
            'attachments' => [
                [
                    'path' => $file,
                    'as' => 'facture.pdf',
                    'mime' => 'application/pdf',
                ],
            ],
        ]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success, sprintf(
            'Mail send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertNull($result->error_message);
    }

    public function test_execute_ignores_invalid_attachment_entries(): void
    {
        $this->clearLog();

        $validFile = $this->createTempFile('Valid content');

        $metadata = new StrictDataObject([
            'attachments' => [
                null,                        // ignoré
                '',                          // ignoré
                42,                          // ignoré
                ['as' => 'x.pdf'],           // sans path → ignoré
                ['path' => $validFile],      // OK
            ],
        ]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success, sprintf(
            'Mail send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
    }

    // ============================================================
    // NEW — metadata: cc / bcc / reply_to
    // ============================================================

    public function test_execute_with_cc_as_string(): void
    {
        $this->clearLog();

        $metadata = new StrictDataObject([
            'cc' => 'comptable@afya.com',
        ]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success);
        $this->assertNull($result->error_message);
    }

    public function test_execute_with_cc_as_array(): void
    {
        $this->clearLog();

        $metadata = new StrictDataObject([
            'cc' => [
                'comptable@afya.com',
                'manager@afya.com',
                '',          // ignoré (vide)
                42,          // ignoré (non-string)
            ],
        ]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success);
    }

    public function test_execute_with_bcc(): void
    {
        $this->clearLog();

        $metadata = new StrictDataObject([
            'bcc' => 'audit@afya.com',
        ]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success);
    }

    public function test_execute_with_reply_to(): void
    {
        $this->clearLog();

        $metadata = new StrictDataObject([
            'reply_to' => 'support@afya.com',
        ]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success);
    }

    // ============================================================
    // NEW — metadata: priority
    // ============================================================

    public function test_execute_with_priority_1(): void
    {
        $this->clearLog();

        $metadata = new StrictDataObject(['priority' => 1]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success);
    }

    public function test_execute_with_priority_5(): void
    {
        $this->clearLog();

        $metadata = new StrictDataObject(['priority' => 5]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success);
    }

    public function test_execute_ignores_invalid_priority(): void
    {
        $this->clearLog();

        $metadata = new StrictDataObject(['priority' => 99]); // hors [1,5]

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success);
    }

    // ============================================================
    // NEW — metadata: from override
    // ============================================================

    public function test_execute_with_from_override(): void
    {
        $this->clearLog();

        $metadata = new StrictDataObject([
            'from' => 'facturation@afya.com',
            'from_name' => 'Service Facturation',
        ]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success);

        $log = $this->getLogContent();
        $this->assertStringContainsString('facturation@afya.com', $log);
    }

    public function test_execute_with_from_but_no_name_uses_config_name(): void
    {
        $this->clearLog();

        $metadata = new StrictDataObject([
            'from' => 'facturation@afya.com',
        ]);

        $result = $this->driver->send(
            $this->makeMessage(metadata: $metadata),
            $this->route,
        );

        $this->assertTrue($result->success);

        $log = $this->getLogContent();
        $this->assertStringContainsString('facturation@afya.com', $log);
        $this->assertStringContainsString('Test App', $log);
    }

    // ============================================================
    // NEW — metadata: combiné
    // ============================================================

    public function test_execute_with_all_metadata(): void
    {
        $this->clearLog();

        $file1 = $this->createTempFile('Facture content');
        $file2 = $this->createTempFile('Summary content');

        $metadata = new StrictDataObject([
            'attachments' => [
                $file1,
                ['path' => $file2, 'as' => 'summary.pdf'],
            ],
            'cc' => ['comptable@afya.com'],
            'bcc' => 'audit@afya.com',
            'reply_to' => 'support@afya.com',
            'priority' => 1,
            'from' => 'facturation@afya.com',
            'from_name' => 'Facturation',
        ]);

        $result = $this->driver->send(
            $this->makeMessage(
                body: '<h1>Facture</h1>',
                subject: 'Facture #1234',
                metadata: $metadata,
            ),
            $this->route,
        );

        $this->assertTrue($result->success, sprintf(
            'Mail send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertNull($result->error_message);
    }

    // ============================================================
    // Error handling
    // ============================================================

    public function test_execute_saves_error_in_send_result_on_exception(): void
    {
        $this->clearLog();

        Mail::shouldReceive('send')
            ->andThrow(new \Exception('SMTP connection failed'));

        $result = $this->driver->send($this->makeMessage(), $this->route);

        $this->assertFalse($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());
        $this->assertEquals('john@example.com', $result->destination);
        $this->assertNotNull($result->error_message);
        $this->assertStringContainsString(
            'SMTP connection failed',
            $result->error_message->getValue(),
        );
    }
}
