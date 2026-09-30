<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Builders;

use AndyDefer\LaravelNotification\Builders\NotificationRouteBuilder;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Channels\WebPushChannel;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\FakeNotifiableModel;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\LaravelNotification\ValueObjects\PusherChannelNameVO;
use InvalidArgumentException;

final class NotificationRouteBuilderTest extends TestCase
{
    // ============================================================
    // MAIL
    // ============================================================

    public function test_add_mail_adds_a_route(): void
    {
        $collection = NotificationRouteBuilder::make()
            ->addMail('user@example.com')
            ->build();

        $this->assertCount(1, $collection);
        $this->assertSame(MailChannel::class, $collection->first()->getChannelClass());
        $this->assertSame('user@example.com', $collection->first()->getDestination());
    }

    public function test_add_mail_ignores_null_email(): void
    {
        $collection = NotificationRouteBuilder::make()->addMail(null)->build();

        $this->assertCount(0, $collection);
    }

    public function test_add_mail_ignores_empty_email(): void
    {
        $collection = NotificationRouteBuilder::make()->addMail('')->build();

        $this->assertCount(0, $collection);
    }

    public function test_add_mail_is_unique_per_destination(): void
    {
        $collection = NotificationRouteBuilder::make()
            ->addMail('user@example.com')
            ->addMail('user@example.com')
            ->build();

        $this->assertCount(1, $collection);
    }

    public function test_add_mails_adds_one_route_per_email(): void
    {
        $collection = NotificationRouteBuilder::make()
            ->addMails(['a@example.com', 'b@example.com', 'c@example.com'])
            ->build();

        $this->assertCount(3, $collection);
    }

    public function test_add_mails_deduplicates_emails(): void
    {
        $collection = NotificationRouteBuilder::make()
            ->addMails(['a@example.com', 'a@example.com', 'b@example.com'])
            ->build();

        $this->assertCount(2, $collection);
    }

    public function test_add_mails_rejects_non_string_entries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NotificationRouteBuilder::make()->addMails([123, 'ok@example.com']);
    }

    // ============================================================
    // DATABASE
    // ============================================================

    public function test_add_database_adds_a_route(): void
    {
        $collection = NotificationRouteBuilder::make()->addDatabase()->build();

        $this->assertCount(1, $collection);
        $this->assertSame(DatabaseChannel::class, $collection->first()->getChannelClass());
        $this->assertSame('database', $collection->first()->getDestination());
    }

    public function test_add_database_is_unique(): void
    {
        $collection = NotificationRouteBuilder::make()
            ->addDatabase()
            ->addDatabase()
            ->build();

        $this->assertCount(1, $collection);
    }

    // ============================================================
    // FCM
    // ============================================================

    public function test_add_fcm_adds_a_route(): void
    {
        $device = new FcmDevice([
            'token' => 'token-abc',
            'device_id' => 'device-1',
        ]);

        $collection = NotificationRouteBuilder::make()->addFcm($device)->build();

        $this->assertCount(1, $collection);
        $this->assertSame(FirebaseCloudMessagingChannel::class, $collection->first()->getChannelClass());
        $this->assertSame('token-abc', $collection->first()->getDestination());
    }

    public function test_add_fcm_is_unique_per_token(): void
    {
        $device = new FcmDevice([
            'token' => 'token-abc',
            'device_id' => 'device-1',
        ]);

        $collection = NotificationRouteBuilder::make()
            ->addFcm($device)
            ->addFcm($device)
            ->build();

        $this->assertCount(1, $collection);
    }

    public function test_add_fcms_adds_one_route_per_device(): void
    {
        $deviceA = new FcmDevice(['token' => 'token-a', 'device_id' => 'device-a']);
        $deviceB = new FcmDevice(['token' => 'token-b', 'device_id' => 'device-b']);

        $collection = NotificationRouteBuilder::make()
            ->addFcms([$deviceA, $deviceB])
            ->build();

        $this->assertCount(2, $collection);
    }

    public function test_add_fcms_deduplicates_tokens(): void
    {
        $deviceA = new FcmDevice(['token' => 'token-a', 'device_id' => 'device-a']);
        $deviceB = new FcmDevice(['token' => 'token-a', 'device_id' => 'device-b']);

        $collection = NotificationRouteBuilder::make()
            ->addFcms([$deviceA, $deviceB])
            ->build();

        $this->assertCount(1, $collection);
    }

    public function test_add_fcms_rejects_non_fcm_device_entries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NotificationRouteBuilder::make()->addFcms(['not-a-device']);
    }

    // ============================================================
    // WEB PUSH
    // ============================================================

    public function test_add_web_push_adds_a_route(): void
    {
        $subscription = new WebPushSubscription([
            'endpoint' => 'https://push.example.com/abc',
            'p256dh' => 'p256dh-key',
            'auth' => 'auth-secret',
        ]);

        $collection = NotificationRouteBuilder::make()
            ->addWebPush($subscription)
            ->build();

        $this->assertCount(1, $collection);
        $this->assertSame(WebPushChannel::class, $collection->first()->getChannelClass());
        $this->assertSame('https://push.example.com/abc', $collection->first()->getDestination());
    }

    public function test_add_web_push_ignores_incomplete_subscription(): void
    {
        $subscription = new WebPushSubscription([
            'endpoint' => 'https://push.example.com/abc',
            'p256dh' => '',
            'auth' => 'auth-secret',
        ]);

        $collection = NotificationRouteBuilder::make()
            ->addWebPush($subscription)
            ->build();

        $this->assertCount(0, $collection);
    }

    public function test_add_web_push_is_unique_per_endpoint(): void
    {
        $subscription = new WebPushSubscription([
            'endpoint' => 'https://push.example.com/abc',
            'p256dh' => 'p256dh-key',
            'auth' => 'auth-secret',
        ]);

        $collection = NotificationRouteBuilder::make()
            ->addWebPush($subscription)
            ->addWebPush($subscription)
            ->build();

        $this->assertCount(1, $collection);
    }

    public function test_add_web_pushes_adds_one_route_per_subscription(): void
    {
        $a = new WebPushSubscription([
            'endpoint' => 'https://push.example.com/a',
            'p256dh' => 'key-a',
            'auth' => 'auth-a',
        ]);
        $b = new WebPushSubscription([
            'endpoint' => 'https://push.example.com/b',
            'p256dh' => 'key-b',
            'auth' => 'auth-b',
        ]);

        $collection = NotificationRouteBuilder::make()
            ->addWebPushes([$a, $b])
            ->build();

        $this->assertCount(2, $collection);
    }

    public function test_add_web_pushes_rejects_non_subscription_entries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NotificationRouteBuilder::make()->addWebPushes([new \stdClass]);
    }

    // ============================================================
    // PUSHER
    // ============================================================

    public function test_add_pusher_adds_a_route(): void
    {
        $collection = NotificationRouteBuilder::make()->addPusher('announcements')->build();

        $this->assertCount(1, $collection);
        $this->assertSame(PusherChannel::class, $collection->first()->getChannelClass());
        $this->assertSame('announcements', $collection->first()->getDestination());
    }

    public function test_add_pusher_ignores_empty_channel(): void
    {
        $collection = NotificationRouteBuilder::make()->addPusher('')->build();

        $this->assertCount(0, $collection);
    }

    public function test_add_pusher_is_unique_per_channel(): void
    {
        $collection = NotificationRouteBuilder::make()
            ->addPusher('announcements')
            ->addPusher('announcements', 'other-event')
            ->build();

        $this->assertCount(1, $collection);
    }

    public function test_add_pusher_for_model_uses_pusher_channel_name_vo(): void
    {
        $model = new FakeNotifiableModel('abc-123');

        $collection = NotificationRouteBuilder::make()
            ->addPusherForModel($model)
            ->build();

        $this->assertCount(1, $collection);

        /** @var NotificationRouteVO $route */
        $route = $collection->first();

        $expected = PusherChannelNameVO::forModel($model)->getValue();

        $this->assertSame(PusherChannel::class, $route->getChannelClass());
        $this->assertSame($expected, $route->getDestination());
    }

    public function test_add_pusher_for_model_is_unique(): void
    {
        $model = new FakeNotifiableModel('abc-123');

        $collection = NotificationRouteBuilder::make()
            ->addPusherForModel($model)
            ->addPusherForModel($model)
            ->build();

        $this->assertCount(1, $collection);
    }

    // ============================================================
    // COMBINED
    // ============================================================

    public function test_full_pipeline_builds_unique_routes(): void
    {
        $deviceA = new FcmDevice(['token' => 'token-a', 'device_id' => 'device-a']);
        $deviceB = new FcmDevice(['token' => 'token-b', 'device_id' => 'device-b']);

        $subA = new WebPushSubscription([
            'endpoint' => 'https://push.example.com/a',
            'p256dh' => 'key-a',
            'auth' => 'auth-a',
        ]);

        $collection = NotificationRouteBuilder::make()
            ->addMail('user@example.com')
            ->addMail('user@example.com')
            ->addDatabase()
            ->addDatabase()
            ->addFcms([$deviceA, $deviceB])
            ->addFcms([$deviceA])
            ->addWebPushes([$subA])
            ->addPusher('announcements')
            ->addPusher('announcements')
            ->build();

        $this->assertCount(6, $collection);

        $channelCounts = [];
        foreach ($collection as $route) {
            /** @var NotificationRouteVO $route */
            $channelCounts[$route->getChannelClass()] = ($channelCounts[$route->getChannelClass()] ?? 0) + 1;
        }

        $this->assertSame(1, $channelCounts[MailChannel::class]);
        $this->assertSame(1, $channelCounts[DatabaseChannel::class]);
        $this->assertSame(2, $channelCounts[FirebaseCloudMessagingChannel::class]);
        $this->assertSame(1, $channelCounts[WebPushChannel::class]);
        $this->assertSame(1, $channelCounts[PusherChannel::class]);
    }
}
