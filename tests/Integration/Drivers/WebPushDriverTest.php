<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Drivers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\WebPushChannel;
use AndyDefer\LaravelNotification\Drivers\WebPushDriver;
use AndyDefer\LaravelNotification\Records\WebPushConfigRecord;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

final class WebPushDriverTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint;

    private string $p256dh;

    private string $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = (string) $this->getEnv('WEBPUSH_ENDPOINT');
        $this->p256dh = (string) $this->getEnv('WEBPUSH_P256DH');
        $this->auth = (string) $this->getEnv('WEBPUSH_AUTH');
    }

    private function makeConfig(
        bool $enabled = true,
        ?string $subject = 'mailto:contact@afya-medical.com',
        ?string $publicKey = 'BDw92Y6vnPXYNN90QwiqmLAnnEX9GJZo27by3Bpo2nATkEAivqJIf436LV_Vc8Hg7S2fkzJp3Ow_5p0CVCVTX5g',
        ?string $privateKey = 'm7lTHzndL5P7QL0be8a0l1whYsy8pFhKJD6dYtRvUpA',
        int $ttl = 3600,
        string $urgency = 'normal',
        string $topic = 'notification',
    ): WebPushConfigRecord {
        return new WebPushConfigRecord(
            enabled: $enabled,
            subject: $subject,
            public_key: $publicKey,
            private_key: $privateKey,
            ttl: $ttl,
            urgency: $urgency,
            topic: $topic,
        );
    }

    private function makeMessage(): NotificationMessageVO
    {
        return new NotificationMessageVO(
            body: new MessageBodyVO('Ceci est un test Web Push depuis Laravel.'),
            subject: new MessageSubjectVO('Test Web Push'),
            type: 'test',
            data: new StrictDataObject([
                'screen' => 'home',
                'url' => '/',
            ]),
        );
    }

    private function makeRoute(array $metadata = []): NotificationRouteVO
    {
        return new NotificationRouteVO(
            channelClass: WebPushChannel::class,
            destination: $this->endpoint,
            metadata: new StrictDataObject(array_merge([
                'endpoint' => $this->endpoint,
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
                'title' => 'Test Web Push',
                'data' => ['url' => '/'],
            ], $metadata)),
        );
    }

    public function test_get_channel_returns_webpush(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $this->assertSame('webpush', $driver->getChannel());
    }

    public function test_validate_configuration_with_valid_config(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $this->assertTrue($driver->validateConfiguration());
    }

    public function test_validate_configuration_when_disabled(): void
    {
        $driver = new WebPushDriver($this->makeConfig(enabled: false));

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_validate_configuration_without_subject(): void
    {
        $driver = new WebPushDriver($this->makeConfig(subject: null));

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_validate_configuration_without_public_key(): void
    {
        $driver = new WebPushDriver($this->makeConfig(publicKey: null));

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_validate_configuration_without_private_key(): void
    {
        $driver = new WebPushDriver($this->makeConfig(privateKey: null));

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_execute_with_empty_configuration_throws_exception(): void
    {
        $driver = new WebPushDriver($this->makeConfig(
            enabled: true,
            subject: null,
            publicKey: null,
            privateKey: null,
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Driver AndyDefer\LaravelNotification\Drivers\WebPushDriver configuration is invalid.',
        );

        $driver->send($this->makeMessage(), $this->makeRoute());
    }

    public function test_execute_sends_push_notification(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $result = $driver->send($this->makeMessage(), $this->makeRoute());

        $this->assertTrue($result->success, sprintf(
            'Web Push send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertEquals(WebPushChannel::class, $result->channel->getValue());
        $this->assertEquals($this->endpoint, $result->destination);
        $this->assertNull($result->error_message);
    }

    public function test_execute_sends_push_notification_with_custom_title(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $route = $this->makeRoute(['title' => 'Custom Title']);

        $result = $driver->send($this->makeMessage(), $route);

        $this->assertTrue($result->success, sprintf(
            'Web Push send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertNull($result->error_message);
    }

    public function test_execute_fails_when_p256dh_is_missing(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $route = new NotificationRouteVO(
            channelClass: WebPushChannel::class,
            destination: $this->endpoint,
            metadata: new StrictDataObject([
                'endpoint' => $this->endpoint,
                'auth' => $this->auth,
            ]),
        );

        $result = $driver->send($this->makeMessage(), $route);

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error_message);
        $this->assertStringContainsString('p256dh', $result->error_message->getValue());
    }

    public function test_execute_fails_when_auth_is_missing(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $route = new NotificationRouteVO(
            channelClass: WebPushChannel::class,
            destination: $this->endpoint,
            metadata: new StrictDataObject([
                'endpoint' => $this->endpoint,
                'p256dh' => $this->p256dh,
            ]),
        );

        $result = $driver->send($this->makeMessage(), $route);

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error_message);
        $this->assertStringContainsString('auth', $result->error_message->getValue());
    }

    public function test_execute_saves_error_when_endpoint_is_invalid(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $invalid = 'https://invalid-endpoint-that-does-not-exist.example/fake';

        $route = new NotificationRouteVO(
            channelClass: WebPushChannel::class,
            destination: $invalid,
            metadata: new StrictDataObject([
                'endpoint' => $invalid,
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ]),
        );

        $result = $driver->send($this->makeMessage(), $route);

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error_message);
    }
}
