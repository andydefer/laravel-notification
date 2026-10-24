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
use RuntimeException;

final class WebPushDriverTest extends TestCase
{
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

    private function makeMessage(
        string $body = 'Ceci est un test Web Push.',
        string $subject = 'Test Web Push',
        string $type = 'test',
        ?StrictDataObject $data = null,
        ?StrictDataObject $metadata = null,
    ): NotificationMessageVO {
        return new NotificationMessageVO(
            body: new MessageBodyVO($body),
            subject: new MessageSubjectVO($subject),
            type: $type,
            data: $data ?? new StrictDataObject(['url' => '/']),
            metadata: $metadata,
        );
    }

    private function makeRoute(?StrictDataObject $extraMetadata = null): NotificationRouteVO
    {
        $baseMetadata = [
            'endpoint' => $this->endpoint,
            'p256dh' => $this->p256dh,
            'auth' => $this->auth,
        ];

        $merged = $extraMetadata === null
            ? $baseMetadata
            : array_merge($baseMetadata, $extraMetadata->toArray());

        return new NotificationRouteVO(
            channelClass: WebPushChannel::class,
            destination: $this->endpoint,
            metadata: new StrictDataObject($merged),
        );
    }

    // ============================================================
    // Channel + Validation
    // ============================================================

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

    // ============================================================
    // Basic send
    // ============================================================

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

    // ============================================================
    // NEW — metadata: TTL
    // ============================================================

    public function test_execute_with_custom_ttl_from_message_metadata(): void
    {
        $driver = new WebPushDriver($this->makeConfig(ttl: 3600));

        $message = $this->makeMessage(
            metadata: new StrictDataObject(['ttl' => 60]),
        );

        $result = $driver->send($message, $this->makeRoute());

        $this->assertTrue($result->success, sprintf(
            'Web Push send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
    }

    public function test_execute_falls_back_to_config_ttl_when_metadata_absent(): void
    {
        $driver = new WebPushDriver($this->makeConfig(ttl: 3600));

        $result = $driver->send($this->makeMessage(), $this->makeRoute());

        $this->assertTrue($result->success);
    }

    public function test_execute_ignores_negative_ttl(): void
    {
        $driver = new WebPushDriver($this->makeConfig(ttl: 3600));

        $message = $this->makeMessage(
            metadata: new StrictDataObject(['ttl' => -1]),
        );

        $result = $driver->send($message, $this->makeRoute());

        // Fallback config (3600), pas d'erreur
        $this->assertTrue($result->success);
    }

    public function test_execute_ignores_non_int_ttl(): void
    {
        $driver = new WebPushDriver($this->makeConfig(ttl: 3600));

        $message = $this->makeMessage(
            metadata: new StrictDataObject(['ttl' => '3600']), // string, pas int
        );

        $result = $driver->send($message, $this->makeRoute());

        $this->assertTrue($result->success);
    }

    // ============================================================
    // NEW — metadata: urgency
    // ============================================================

    public function test_execute_with_urgency_high(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $message = $this->makeMessage(
            metadata: new StrictDataObject(['urgency' => 'high']),
        );

        $result = $driver->send($message, $this->makeRoute());

        $this->assertTrue($result->success);
    }

    public function test_execute_with_urgency_low(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $message = $this->makeMessage(
            metadata: new StrictDataObject(['urgency' => 'low']),
        );

        $result = $driver->send($message, $this->makeRoute());

        $this->assertTrue($result->success);
    }

    public function test_execute_with_urgency_very_low(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $message = $this->makeMessage(
            metadata: new StrictDataObject(['urgency' => 'very-low']),
        );

        $result = $driver->send($message, $this->makeRoute());

        $this->assertTrue($result->success);
    }

    public function test_execute_falls_back_to_config_urgency_when_invalid(): void
    {
        $driver = new WebPushDriver($this->makeConfig(urgency: 'normal'));

        $message = $this->makeMessage(
            metadata: new StrictDataObject(['urgency' => 'ultra-high']), // invalide
        );

        $result = $driver->send($message, $this->makeRoute());

        // Fallback 'normal', pas d'erreur
        $this->assertTrue($result->success);
    }

    // ============================================================
    // NEW — metadata: topic
    // ============================================================

    public function test_execute_with_custom_topic(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $message = $this->makeMessage(
            metadata: new StrictDataObject(['topic' => 'alerts']),
        );

        $result = $driver->send($message, $this->makeRoute());

        $this->assertTrue($result->success);
    }

    public function test_execute_falls_back_to_config_topic_when_empty(): void
    {
        $driver = new WebPushDriver($this->makeConfig(topic: 'notification'));

        $message = $this->makeMessage(
            metadata: new StrictDataObject(['topic' => '']), // vide
        );

        $result = $driver->send($message, $this->makeRoute());

        $this->assertTrue($result->success);
    }

    // ============================================================
    // NEW — metadata: combiné
    // ============================================================

    public function test_execute_with_all_transport_options_in_message(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $message = $this->makeMessage(
            metadata: new StrictDataObject([
                'ttl' => 120,
                'urgency' => 'high',
                'topic' => 'alerts',
            ]),
        );

        $result = $driver->send($message, $this->makeRoute());

        $this->assertTrue($result->success);
        $this->assertNull($result->error_message);
    }

    // ============================================================
    // Device metadata validation (route)
    // ============================================================

    public function test_execute_fails_when_p256dh_is_missing(): void
    {
        $driver = new WebPushDriver($this->makeConfig());

        $route = new NotificationRouteVO(
            channelClass: WebPushChannel::class,
            destination: $this->endpoint,
            metadata: new StrictDataObject([
                'endpoint' => $this->endpoint,
                'auth' => $this->auth,
                // p256dh manquant
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
                // auth manquant
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
