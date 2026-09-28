<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelNotification\Directives\GenerateVapidKeysDirective;
use AndyDefer\LaravelNotification\Tests\TestCase;

final class GenerateVapidKeysDirectiveTest extends TestCase
{
    private DirectiveTestingService $directiveService;

    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = sys_get_temp_dir().'/vapid-test-'.uniqid().'.env';

        file_put_contents($this->envPath, "APP_NAME=Test\n");

        $this->directiveService = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: [],
        );

        $this->directiveService
            ->getKernel()
            ->addDirective(GenerateVapidKeysDirective::class);
    }

    protected function tearDown(): void
    {
        if (is_file($this->envPath)) {
            @unlink($this->envPath);
        }

        $this->directiveService->destroy();
        parent::tearDown();
    }

    private function envContents(): string
    {
        return (string) file_get_contents($this->envPath);
    }

    private function extractValue(string $key): ?string
    {
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $this->envContents(), $m) !== 1) {
            return null;
        }

        return trim($m[1], " \t\"'");
    }

    public function test_generates_keys_when_absent(): void
    {
        $response = $this->directiveService->run(
            'notification:generate-vapid '.$this->envPath,
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('VAPID keys generated', $response->output);

        $public = $this->extractValue('WEBPUSH_PUBLIC_KEY');
        $private = $this->extractValue('WEBPUSH_PRIVATE_KEY');

        $this->assertNotNull($public);
        $this->assertNotNull($private);
        $this->assertNotEmpty($public);
        $this->assertNotEmpty($private);
    }

    public function test_adds_default_env_keys_when_absent(): void
    {
        $this->directiveService->run(
            'notification:generate-vapid '.$this->envPath,
        );

        $this->assertSame('true', $this->extractValue('WEBPUSH_NOTIFICATION_ENABLED'));
        $this->assertNotNull($this->extractValue('WEBPUSH_SUBJECT'));
    }

    public function test_does_not_overwrite_existing_keys_without_force(): void
    {
        file_put_contents($this->envPath, implode(PHP_EOL, [
            'APP_NAME=Test',
            'WEBPUSH_PUBLIC_KEY=EXISTING_PUBLIC',
            'WEBPUSH_PRIVATE_KEY=EXISTING_PRIVATE',
            '',
        ]));

        $response = $this->directiveService->run(
            'notification:generate-vapid '.$this->envPath,
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('already exist', $response->output);
        $this->assertSame('EXISTING_PUBLIC', $this->extractValue('WEBPUSH_PUBLIC_KEY'));
        $this->assertSame('EXISTING_PRIVATE', $this->extractValue('WEBPUSH_PRIVATE_KEY'));
    }

    public function test_force_overwrites_existing_keys(): void
    {
        file_put_contents($this->envPath, implode(PHP_EOL, [
            'APP_NAME=Test',
            'WEBPUSH_PUBLIC_KEY=EXISTING_PUBLIC',
            'WEBPUSH_PRIVATE_KEY=EXISTING_PRIVATE',
            '',
        ]));

        $response = $this->directiveService->run(
            'notification:generate-vapid '.$this->envPath.' --force',
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('VAPID keys generated', $response->output);
        $this->assertNotSame('EXISTING_PUBLIC', $this->extractValue('WEBPUSH_PUBLIC_KEY'));
        $this->assertNotSame('EXISTING_PRIVATE', $this->extractValue('WEBPUSH_PRIVATE_KEY'));
    }

    public function test_generates_distinct_keys_on_each_run(): void
    {
        $this->directiveService->run(
            'notification:generate-vapid '.$this->envPath,
        );
        $firstPublic = $this->extractValue('WEBPUSH_PUBLIC_KEY');

        $this->directiveService->run(
            'notification:generate-vapid '.$this->envPath.' --force',
        );
        $secondPublic = $this->extractValue('WEBPUSH_PUBLIC_KEY');

        $this->assertNotSame($firstPublic, $secondPublic);
    }

    public function test_preserves_unrelated_env_keys(): void
    {
        file_put_contents($this->envPath, implode(PHP_EOL, [
            'APP_NAME=Test',
            'APP_KEY=base64:fake',
            'DB_CONNECTION=mysql',
            '',
        ]));

        $this->directiveService->run(
            'notification:generate-vapid '.$this->envPath,
        );

        $contents = $this->envContents();

        $this->assertStringContainsString('APP_NAME=Test', $contents);
        $this->assertStringContainsString('APP_KEY=base64:fake', $contents);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $contents);
    }

    public function test_public_key_length_is_valid(): void
    {
        $this->directiveService->run(
            'notification:generate-vapid '.$this->envPath,
        );

        $public = (string) $this->extractValue('WEBPUSH_PUBLIC_KEY');

        $this->assertGreaterThanOrEqual(85, strlen($public));
        $this->assertLessThanOrEqual(90, strlen($public));
    }

    public function test_private_key_length_is_valid(): void
    {
        $this->directiveService->run(
            'notification:generate-vapid '.$this->envPath,
        );

        $private = (string) $this->extractValue('WEBPUSH_PRIVATE_KEY');

        $this->assertGreaterThanOrEqual(40, strlen($private));
        $this->assertLessThanOrEqual(48, strlen($private));
    }

    public function test_alias_gvk_works(): void
    {
        $response = $this->directiveService->run(
            'notification:gvk '.$this->envPath,
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertNotNull($this->extractValue('WEBPUSH_PUBLIC_KEY'));
    }

    public function test_alias_n_gvk_works(): void
    {
        $response = $this->directiveService->run(
            'n:gvk '.$this->envPath,
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertNotNull($this->extractValue('WEBPUSH_PRIVATE_KEY'));
    }
}
