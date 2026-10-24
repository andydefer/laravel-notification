<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Drivers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Drivers\FirebaseCloudMessagingDriver;
use AndyDefer\LaravelNotification\Records\FirebaseConfigRecord;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class FirebaseCloudMessagingDriverTest extends TestCase
{
    private string $deviceToken;

    private FirebaseCloudMessagingDriver $driver;

    private FirebaseConfigRecord $config;

    private NotificationRouteVO $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceToken = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        $this->config = new FirebaseConfigRecord(
            enabled: true,
            credentials_path: (string) $this->getEnv('FIREBASE_CREDENTIALS_PATH'),
            project_id: (string) $this->getEnv('FIREBASE_PROJECT_ID'),
            scope: 'https://www.googleapis.com/auth/firebase.messaging',
            timeout: (int) $this->getEnv('FIREBASE_TIMEOUT'),
        );

        $this->driver = new FirebaseCloudMessagingDriver($this->config);

        // ⬇️ La route ne porte QUE le token — plus aucun champ visuel ni data
        $this->route = new NotificationRouteVO(
            channelClass: FirebaseCloudMessagingChannel::class,
            destination: $this->deviceToken,
        );
    }

    public function test_get_channel_returns_firebase(): void
    {
        $this->assertEquals('firebase', $this->driver->getChannel());
    }

    public function test_validate_configuration_with_valid_config(): void
    {
        $this->assertTrue($this->driver->validateConfiguration());
    }

    public function test_validate_configuration_when_disabled(): void
    {
        $config = new FirebaseConfigRecord(
            enabled: false,
            credentials_path: (string) $this->getEnv('FIREBASE_CREDENTIALS_PATH'),
            project_id: (string) $this->getEnv('FIREBASE_PROJECT_ID'),
        );
        $driver = new FirebaseCloudMessagingDriver($config);

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_validate_configuration_without_credentials_path(): void
    {
        $config = new FirebaseConfigRecord(
            enabled: true,
            credentials_path: null,
            project_id: (string) $this->getEnv('FIREBASE_PROJECT_ID'),
        );
        $driver = new FirebaseCloudMessagingDriver($config);

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_validate_configuration_with_missing_file(): void
    {
        $config = new FirebaseConfigRecord(
            enabled: true,
            credentials_path: '/nonexistent/path/firebase.json',
            project_id: (string) $this->getEnv('FIREBASE_PROJECT_ID'),
        );
        $driver = new FirebaseCloudMessagingDriver($config);

        $this->assertFalse($driver->validateConfiguration());
    }

    public function test_execute_with_empty_configuration_throws_exception(): void
    {
        $config = new FirebaseConfigRecord(
            enabled: true,
            credentials_path: null,
            project_id: null,
        );
        $driver = new FirebaseCloudMessagingDriver($config);

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Driver AndyDefer\LaravelNotification\Drivers\FirebaseCloudMessagingDriver configuration is invalid.',
        );

        $driver->send($message, $this->route);
    }

    public function test_execute_sends_push_notification(): void
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Ceci est un message envoyé depuis du PHP pur.'),
            subject: new MessageSubjectVO('Bonjour ! 👋'),
            type: 'test',
            // ⬇️ Valeurs brutes, PAS de json_encode
            data: new StrictDataObject([
                'screen' => 'profile',
                'user_id' => '42',
            ]),
        );

        $result = $this->driver->send($message, $this->route);

        $this->assertTrue($result->success, sprintf(
            'Firebase send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertEquals(FirebaseCloudMessagingChannel::class, $result->channel->getValue());
        $this->assertEquals($this->deviceToken, $result->destination);
        $this->assertNull($result->error_message);
    }

    public function test_execute_sends_push_notification_with_visual_metadata(): void
    {
        // ⬇️ Les champs visuels vont dans `metadata`, pas dans `data`
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Notification avec métadonnées visuelles.'),
            subject: new MessageSubjectVO('Titre visuel'),
            type: 'order_notification',
            data: new StrictDataObject([
                'order_id' => '99',
            ]),
            metadata: new StrictDataObject([
                'icon' => 'ic_notification',
                'image' => 'https://cdn.afya.com/banner.png',
                'channel_id' => 'afya_payments',
                'sound' => 'default',
                'tag' => 'orders',
            ]),
        );

        $result = $this->driver->send($message, $this->route);

        $this->assertTrue($result->success, sprintf(
            'Firebase send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertNull($result->error_message);
    }

    public function test_execute_sends_push_notification_with_complex_data_payload(): void
    {
        // ⬇️ Arrays natifs — le driver fait json_encode UNE SEULE FOIS
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Notification avec payload complexe.'),
            subject: new MessageSubjectVO('Payload complexe'),
            type: 'profile_updated',
            data: new StrictDataObject([
                'user_id' => '61',
                'updated_fields' => ['currency'],           // array → json 1 fois
                'roles' => ['user', 'admin'],
                'count' => 3,                       // scalar → cast string
            ]),
        );

        $result = $this->driver->send($message, $this->route);

        $this->assertTrue($result->success, sprintf(
            'Firebase send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertNull($result->error_message);
    }

    public function test_execute_sends_push_notification_with_empty_metadata(): void
    {
        // ⬇️ metadata = null → notification contient juste title + body
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Sans métadonnées visuelles.'),
            subject: new MessageSubjectVO('Sans metadata'),
            type: 'test',
            data: new StrictDataObject([
                'screen' => 'home',
            ]),
            metadata: null,
        );

        $result = $this->driver->send($message, $this->route);

        $this->assertTrue($result->success, sprintf(
            'Firebase send failed: %s',
            $result->error_message?->getValue() ?? 'unknown error',
        ));
        $this->assertNull($result->error_message);
    }

    public function test_execute_saves_error_when_device_token_is_invalid(): void
    {
        $route = new NotificationRouteVO(
            channelClass: FirebaseCloudMessagingChannel::class,
            destination: str_repeat('a', 200),
        );

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Test'),
            subject: new MessageSubjectVO('Subject'),
            type: 'test',
        );

        $result = $this->driver->send($message, $route);

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error_message);
        $this->assertStringContainsString(
            'Firebase trigger failed',
            $result->error_message->getValue(),
        );
    }
}
