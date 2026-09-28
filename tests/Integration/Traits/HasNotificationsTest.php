<?php

// tests/Integration/Traits/HasNotificationsTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Traits;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\UuidVO;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class HasNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    private function createMessage(
        string $body = 'Test message',
        string $subject = 'Test Subject',
        string $type = 'test',
    ): NotificationMessageVO {
        return new NotificationMessageVO(
            body: new MessageBodyVO($body),
            subject: new MessageSubjectVO($subject),
            type: $type,
            data: StrictDataObject::from([]),
        );
    }

    private function createNotification(
        NotificationStatus $status = NotificationStatus::PENDING,
        bool $read = false,
        ?string $sessionId = null,
    ): Notification {
        $message = $this->createMessage();

        return Notification::factory()
            ->channel(MailChannel::class)
            ->to('test@example.com')
            ->state([
                'session_id' => $sessionId ?? UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
                'status' => $status->value,
                'read_at' => $read ? now() : null,
            ])
            ->create();
    }

    private function createFcmDevice(
        string $token = 'fcm-token-abc',
        ?string $deviceId = null,
        ?TestUser $owner = null,
    ): FcmDevice {
        $owner = $owner ?? $this->user;

        return FcmDevice::create([
            'device_id' => $deviceId ?? UuidVO::generate()->getValue(),
            'token' => $token,
            'platform' => 'web',
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => (string) $owner->getKey(),
        ]);
    }

    private function createWebPushSubscription(
        string $endpoint = 'https://example.com/wpush/v2/abc',
        ?TestUser $owner = null,
    ): WebPushSubscription {
        $owner = $owner ?? $this->user;

        return WebPushSubscription::create([
            'endpoint' => $endpoint,
            'p256dh' => 'p256dh-key-'.bin2hex(random_bytes(8)),
            'auth' => 'auth-key-'.bin2hex(random_bytes(8)),
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => (string) $owner->getKey(),
        ]);
    }

    // ============================================================================
    // Tests - notifications()
    // ============================================================================

    public function test_notifications_returns_morph_many_relation(): void
    {
        $relation = $this->user->notifications();

        $this->assertInstanceOf(MorphMany::class, $relation);
    }

    public function test_notifications_returns_all_user_notifications(): void
    {
        $this->createNotification();
        $this->createNotification();
        $this->createNotification();

        $this->assertCount(3, $this->user->notifications);
    }

    // ============================================================================
    // Tests - fcmDevices()
    // ============================================================================

    public function test_fcm_devices_returns_morph_many_relation(): void
    {
        $relation = $this->user->fcmDevices();

        $this->assertInstanceOf(MorphMany::class, $relation);
    }

    public function test_fcm_devices_returns_all_user_devices(): void
    {
        $this->createFcmDevice('token-1');
        $this->createFcmDevice('token-2');
        $this->createFcmDevice('token-3');

        $this->assertCount(3, $this->user->fcmDevices);
    }

    public function test_fcm_devices_are_scoped_to_user(): void
    {
        $otherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $this->createFcmDevice('token-1');
        $this->createFcmDevice('token-2');
        $this->createFcmDevice('token-other', owner: $otherUser);

        $this->assertEquals(2, $this->user->fcmDevices()->count());
        $this->assertEquals(1, $otherUser->fcmDevices()->count());
    }

    // ============================================================================
    // Tests - fcm_tokens
    // ============================================================================

    public function test_fcm_tokens_returns_empty_collection_when_none(): void
    {
        $tokens = $this->user->fcm_tokens;

        $this->assertInstanceOf(StringTypedCollection::class, $tokens);
        $this->assertCount(0, $tokens);
    }

    public function test_fcm_tokens_returns_all_tokens(): void
    {
        $this->createFcmDevice('token-1');
        $this->createFcmDevice('token-2');

        $tokens = $this->user->fcm_tokens;

        $this->assertInstanceOf(StringTypedCollection::class, $tokens);
        $this->assertCount(2, $tokens);
        $this->assertTrue($tokens->contains('token-1'));
        $this->assertTrue($tokens->contains('token-2'));
    }

    // ============================================================================
    // Tests - has_fcm_devices
    // ============================================================================

    public function test_has_fcm_devices_returns_true_when_devices_exist(): void
    {
        $this->createFcmDevice('token-1');

        $this->assertTrue($this->user->has_fcm_devices);
    }

    public function test_has_fcm_devices_returns_false_when_no_devices(): void
    {
        $this->assertFalse($this->user->has_fcm_devices);
    }

    // ============================================================================
    // Tests - webPushSubscriptions()
    // ============================================================================

    public function test_web_push_subscriptions_returns_morph_many_relation(): void
    {
        $relation = $this->user->webPushSubscriptions();

        $this->assertInstanceOf(MorphMany::class, $relation);
    }

    public function test_web_push_subscriptions_returns_all_user_subscriptions(): void
    {
        $this->createWebPushSubscription('https://example.com/wpush/v2/a');
        $this->createWebPushSubscription('https://example.com/wpush/v2/b');
        $this->createWebPushSubscription('https://example.com/wpush/v2/c');

        $this->assertCount(3, $this->user->webPushSubscriptions);
    }

    public function test_web_push_subscriptions_are_scoped_to_user(): void
    {
        $otherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $this->createWebPushSubscription('https://example.com/wpush/v2/a');
        $this->createWebPushSubscription('https://example.com/wpush/v2/b');
        $this->createWebPushSubscription('https://example.com/wpush/v2/other', owner: $otherUser);

        $this->assertEquals(2, $this->user->webPushSubscriptions()->count());
        $this->assertEquals(1, $otherUser->webPushSubscriptions()->count());
    }

    // ============================================================================
    // Tests - web_push_endpoints
    // ============================================================================

    public function test_web_push_endpoints_returns_empty_collection_when_none(): void
    {
        $endpoints = $this->user->web_push_endpoints;

        $this->assertInstanceOf(StringTypedCollection::class, $endpoints);
        $this->assertCount(0, $endpoints);
    }

    public function test_web_push_endpoints_returns_all_endpoints(): void
    {
        $this->createWebPushSubscription('https://example.com/wpush/v2/a');
        $this->createWebPushSubscription('https://example.com/wpush/v2/b');

        $endpoints = $this->user->web_push_endpoints;

        $this->assertInstanceOf(StringTypedCollection::class, $endpoints);
        $this->assertCount(2, $endpoints);
        $this->assertTrue($endpoints->contains('https://example.com/wpush/v2/a'));
        $this->assertTrue($endpoints->contains('https://example.com/wpush/v2/b'));
    }

    // ============================================================================
    // Tests - has_web_push_subscriptions
    // ============================================================================

    public function test_has_web_push_subscriptions_returns_true_when_subscriptions_exist(): void
    {
        $this->createWebPushSubscription();

        $this->assertTrue($this->user->has_web_push_subscriptions);
    }

    public function test_has_web_push_subscriptions_returns_false_when_no_subscriptions(): void
    {
        $this->assertFalse($this->user->has_web_push_subscriptions);
    }

    // ============================================================================
    // Tests - unread_notifications_count
    // ============================================================================

    public function test_unread_notifications_count_returns_zero_when_no_notifications(): void
    {
        $this->assertEquals(0, $this->user->unread_notifications_count);
    }

    public function test_unread_notifications_count_returns_only_unread(): void
    {
        $this->createNotification(read: false);
        $this->createNotification(read: false);
        $this->createNotification(read: true);

        $this->assertEquals(2, $this->user->unread_notifications_count);
    }

    // ============================================================================
    // Tests - unread_notifications
    // ============================================================================

    public function test_unread_notifications_returns_empty_collection_when_none(): void
    {
        $result = $this->user->unread_notifications;

        $this->assertCount(0, $result);
    }

    public function test_unread_notifications_returns_only_unread(): void
    {
        $this->createNotification(read: false);
        $this->createNotification(read: false);
        $this->createNotification(read: true);

        $result = $this->user->unread_notifications;

        $this->assertCount(2, $result);
        foreach ($result as $notification) {
            $this->assertFalse($notification->isRead());
        }
    }

    // ============================================================================
    // Tests - read_notifications
    // ============================================================================

    public function test_read_notifications_returns_only_read(): void
    {
        $this->createNotification(read: false);
        $this->createNotification(read: true);
        $this->createNotification(read: true);

        $result = $this->user->read_notifications;

        $this->assertCount(2, $result);
        foreach ($result as $notification) {
            $this->assertTrue($notification->isRead());
        }
    }

    // ============================================================================
    // Tests - sent_notifications
    // ============================================================================

    public function test_sent_notifications_returns_only_sent(): void
    {
        $this->createNotification(status: NotificationStatus::SENT);
        $this->createNotification(status: NotificationStatus::PENDING);
        $this->createNotification(status: NotificationStatus::SENT);

        $result = $this->user->sent_notifications;

        $this->assertCount(2, $result);
        foreach ($result as $notification) {
            $this->assertEquals(NotificationStatus::SENT, $notification->getStatus());
        }
    }

    // ============================================================================
    // Tests - pending_notifications
    // ============================================================================

    public function test_pending_notifications_returns_only_pending(): void
    {
        $this->createNotification(status: NotificationStatus::PENDING);
        $this->createNotification(status: NotificationStatus::SENT);
        $this->createNotification(status: NotificationStatus::PENDING);

        $result = $this->user->pending_notifications;

        $this->assertCount(2, $result);
        foreach ($result as $notification) {
            $this->assertEquals(NotificationStatus::PENDING, $notification->getStatus());
        }
    }

    // ============================================================================
    // Tests - failed_notifications
    // ============================================================================

    public function test_failed_notifications_returns_only_failed(): void
    {
        $this->createNotification(status: NotificationStatus::FAILED);
        $this->createNotification(status: NotificationStatus::SENT);
        $this->createNotification(status: NotificationStatus::FAILED);

        $result = $this->user->failed_notifications;

        $this->assertCount(2, $result);
        foreach ($result as $notification) {
            $this->assertEquals(NotificationStatus::FAILED, $notification->getStatus());
        }
    }

    // ============================================================================
    // Tests - latest_notifications
    // ============================================================================

    public function test_latest_notifications_respects_limit(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createNotification();
        }

        $result = $this->user->latest_notifications;

        $this->assertCount(10, $result);
    }

    public function test_latest_notifications_returns_empty_when_no_notifications(): void
    {
        $result = $this->user->latest_notifications;

        $this->assertCount(0, $result);
    }

    // ============================================================================
    // Tests - markNotificationAsRead()
    // ============================================================================

    public function test_mark_notification_as_read(): void
    {
        $notification = $this->createNotification(read: false);

        $this->assertFalse($notification->isRead());

        $result = $this->user->markNotificationAsRead($notification->getId());

        $this->assertTrue($result);

        $notification->refresh();
        $this->assertTrue($notification->isRead());
    }

    public function test_mark_notification_as_read_returns_false_when_not_found(): void
    {
        $result = $this->user->markNotificationAsRead('non-existent-uuid');

        $this->assertFalse($result);
    }

    // ============================================================================
    // Tests - markAllNotificationsAsRead()
    // ============================================================================

    public function test_mark_all_notifications_as_read(): void
    {
        $this->createNotification(read: false);
        $this->createNotification(read: false);
        $this->createNotification(read: false);

        $count = $this->user->markAllNotificationsAsRead();

        $this->assertEquals(3, $count);
        $this->assertEquals(0, $this->user->unread_notifications_count);
    }

    public function test_mark_all_notifications_as_read_returns_zero_when_none_unread(): void
    {
        $this->createNotification(read: true);
        $this->createNotification(read: true);

        $count = $this->user->markAllNotificationsAsRead();

        $this->assertEquals(0, $count);
    }

    // ============================================================================
    // Tests - deleteNotification()
    // ============================================================================

    public function test_delete_notification(): void
    {
        $notification = $this->createNotification();

        $result = $this->user->deleteNotification($notification->getId());

        $this->assertTrue($result);
        $this->assertSoftDeleted('notifications', ['id' => $notification->getId()]);
    }

    public function test_delete_notification_returns_false_when_not_found(): void
    {
        $result = $this->user->deleteNotification('non-existent-uuid');

        $this->assertFalse($result);
    }

    // ============================================================================
    // Tests - deleteAllNotifications()
    // ============================================================================

    public function test_delete_all_notifications(): void
    {
        $this->createNotification();
        $this->createNotification();
        $this->createNotification();

        $count = $this->user->deleteAllNotifications();

        $this->assertEquals(3, $count);
        $this->assertEquals(0, $this->user->notifications()->count());
    }

    // ============================================================================
    // Tests - deleteReadNotifications()
    // ============================================================================

    public function test_delete_read_notifications(): void
    {
        $this->createNotification(read: true);
        $this->createNotification(read: true);
        $this->createNotification(read: false);

        $count = $this->user->deleteReadNotifications();

        $this->assertEquals(2, $count);
        $this->assertEquals(1, $this->user->notifications()->count());
    }

    // ============================================================================
    // Tests - has_unread_notifications
    // ============================================================================

    public function test_has_unread_notifications_returns_true(): void
    {
        $this->createNotification(read: false);

        $this->assertTrue($this->user->has_unread_notifications);
    }

    public function test_has_unread_notifications_returns_false_when_all_read(): void
    {
        $this->createNotification(read: true);

        $this->assertFalse($this->user->has_unread_notifications);
    }

    public function test_has_unread_notifications_returns_false_when_no_notifications(): void
    {
        $this->assertFalse($this->user->has_unread_notifications);
    }

    // ============================================================================
    // Tests - has_notifications
    // ============================================================================

    public function test_has_notifications_returns_true(): void
    {
        $this->createNotification();

        $this->assertTrue($this->user->has_notifications);
    }

    public function test_has_notifications_returns_false_when_none(): void
    {
        $this->assertFalse($this->user->has_notifications);
    }

    // ============================================================================
    // Tests - countNotificationsByStatus()
    // ============================================================================

    public function test_count_notifications_by_status(): void
    {
        $this->createNotification(status: NotificationStatus::PENDING);
        $this->createNotification(status: NotificationStatus::PENDING);
        $this->createNotification(status: NotificationStatus::SENT);
        $this->createNotification(status: NotificationStatus::FAILED);
        $this->createNotification(status: NotificationStatus::FAILED);
        $this->createNotification(status: NotificationStatus::FAILED);

        $this->assertEquals(2, $this->user->countNotificationsByStatus(NotificationStatus::PENDING));
        $this->assertEquals(1, $this->user->countNotificationsByStatus(NotificationStatus::SENT));
        $this->assertEquals(3, $this->user->countNotificationsByStatus(NotificationStatus::FAILED));
    }

    public function test_count_notifications_by_status_returns_zero_when_none(): void
    {
        $this->assertEquals(0, $this->user->countNotificationsByStatus(NotificationStatus::SENT));
    }

    // ============================================================================
    // Tests - Scoping (isolation entre modèles)
    // ============================================================================

    public function test_notifications_are_scoped_to_user(): void
    {
        $otherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $this->createNotification();
        $this->createNotification();

        $message = $this->createMessage();
        Notification::factory()
            ->channel(MailChannel::class)
            ->to('jane@example.com')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $otherUser->getMorphClass(),
                'notifiable_id' => $otherUser->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        $this->assertEquals(2, $this->user->notifications()->count());
        $this->assertEquals(1, $otherUser->notifications()->count());
        $this->assertEquals(2, $this->user->unread_notifications_count);
        $this->assertEquals(1, $otherUser->unread_notifications_count);
    }

    // ============================================================================
    // Tests - Intégration complète
    // ============================================================================

    public function test_complete_notification_workflow(): void
    {
        $this->assertFalse($this->user->has_notifications);
        $this->assertEquals(0, $this->user->unread_notifications_count);

        $n1 = $this->createNotification(status: NotificationStatus::PENDING, read: false);
        $n2 = $this->createNotification(status: NotificationStatus::SENT, read: false);
        $n3 = $this->createNotification(status: NotificationStatus::SENT, read: true);

        $this->assertTrue($this->user->has_notifications);
        $this->assertEquals(2, $this->user->unread_notifications_count);
        $this->assertTrue($this->user->has_unread_notifications);

        $this->user->markNotificationAsRead($n1->getId());
        $this->assertEquals(1, $this->user->unread_notifications_count);

        $this->assertCount(1, $this->user->unread_notifications);
        $this->assertCount(2, $this->user->read_notifications);

        $this->assertEquals(1, $this->user->countNotificationsByStatus(NotificationStatus::PENDING));
        $this->assertEquals(2, $this->user->countNotificationsByStatus(NotificationStatus::SENT));

        $count = $this->user->markAllNotificationsAsRead();
        $this->assertEquals(1, $count);
        $this->assertEquals(0, $this->user->unread_notifications_count);

        $deleted = $this->user->deleteReadNotifications();
        $this->assertEquals(3, $deleted);
        $this->assertEquals(0, $this->user->notifications()->count());
        $this->assertFalse($this->user->has_notifications);
    }

    public function test_complete_fcm_and_webpush_workflow(): void
    {
        $this->assertFalse($this->user->has_fcm_devices);
        $this->assertFalse($this->user->has_web_push_subscriptions);

        $this->createFcmDevice('token-1');
        $this->createFcmDevice('token-2');
        $this->createWebPushSubscription('https://example.com/wpush/v2/a');

        $this->assertTrue($this->user->has_fcm_devices);
        $this->assertTrue($this->user->has_web_push_subscriptions);
        $this->assertCount(2, $this->user->fcm_tokens);
        $this->assertCount(1, $this->user->web_push_endpoints);
    }

    // ============================================================================
    // Tests - notificationsByChannel()
    // ============================================================================

    public function test_notifications_by_channel_returns_empty_collection_when_none(): void
    {
        $result = $this->user->notificationsByChannel(MailChannel::class);

        $this->assertCount(0, $result);
    }

    public function test_notifications_by_channel_returns_only_matching_channel(): void
    {
        $this->createNotification();
        $this->createNotification();
        $this->createNotification();

        $message = $this->createMessage();
        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        $mailNotifications = $this->user->notificationsByChannel(MailChannel::class);
        $databaseNotifications = $this->user->notificationsByChannel(DatabaseChannel::class);

        $this->assertCount(3, $mailNotifications);
        $this->assertCount(1, $databaseNotifications);

        foreach ($mailNotifications as $notification) {
            $this->assertEquals(MailChannel::class, $notification->channel);
        }

        foreach ($databaseNotifications as $notification) {
            $this->assertEquals(DatabaseChannel::class, $notification->channel);
        }
    }

    public function test_notifications_by_channel_respects_limit(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createNotification();
        }

        $result = $this->user->notificationsByChannel(MailChannel::class, 5);

        $this->assertCount(5, $result);
    }

    public function test_notifications_by_channel_default_limit_is_ten(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createNotification();
        }

        $result = $this->user->notificationsByChannel(MailChannel::class);

        $this->assertCount(10, $result);
    }

    public function test_notifications_by_channel_are_scoped_to_user(): void
    {
        $otherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $this->createNotification();
        $this->createNotification();

        $message = $this->createMessage();
        Notification::factory()
            ->channel(MailChannel::class)
            ->to('jane@example.com')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $otherUser->getMorphClass(),
                'notifiable_id' => $otherUser->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        $userNotifications = $this->user->notificationsByChannel(MailChannel::class);
        $otherUserNotifications = $otherUser->notificationsByChannel(MailChannel::class);

        $this->assertCount(2, $userNotifications);
        $this->assertCount(1, $otherUserNotifications);
    }

    public function test_notifications_by_channel_returns_ordered_by_created_at_desc(): void
    {
        $old = $this->createNotification();
        $old->created_at = now()->subDays(2);
        $old->save();

        $recent = $this->createNotification();
        $recent->created_at = now();
        $recent->save();

        $middle = $this->createNotification();
        $middle->created_at = now()->subDay();
        $middle->save();

        $result = $this->user->notificationsByChannel(MailChannel::class);

        $items = $result->values();
        $this->assertEquals($recent->getId(), $items[0]->getId());
        $this->assertEquals($middle->getId(), $items[1]->getId());
        $this->assertEquals($old->getId(), $items[2]->getId());
    }

    public function test_notifications_by_channel_with_limit_higher_than_count(): void
    {
        $this->createNotification();
        $this->createNotification();
        $this->createNotification();

        $result = $this->user->notificationsByChannel(MailChannel::class, 100);

        $this->assertCount(3, $result);
    }

    public function test_notifications_by_channel_with_different_channel_returns_empty(): void
    {
        $this->createNotification();
        $this->createNotification();

        $result = $this->user->notificationsByChannel(SmsChannel::class);

        $this->assertCount(0, $result);
    }

    // ============================================================================
    // Tests - database_notifications
    // ============================================================================

    public function test_database_notifications_returns_empty_collection_when_none(): void
    {
        $result = $this->user->database_notifications;

        $this->assertCount(0, $result);
    }

    public function test_database_notifications_returns_only_database_channel(): void
    {
        $this->createNotification();
        $this->createNotification();

        $message = $this->createMessage();

        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        $result = $this->user->database_notifications;

        $this->assertCount(2, $result);
        foreach ($result as $notification) {
            $this->assertEquals(DatabaseChannel::class, $notification->channel);
        }
    }

    public function test_database_notifications_are_scoped_to_user(): void
    {
        $otherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $message = $this->createMessage();

        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $otherUser->getMorphClass(),
                'notifiable_id' => $otherUser->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        $this->assertCount(1, $this->user->database_notifications);
        $this->assertCount(1, $otherUser->database_notifications);
    }

    // ============================================================================
    // Tests - latest_database_notifications
    // ============================================================================

    public function test_latest_database_notifications_returns_empty_when_none(): void
    {
        $result = $this->user->latest_database_notifications;

        $this->assertCount(0, $result);
    }

    public function test_latest_database_notifications_respects_limit_of_ten(): void
    {
        $message = $this->createMessage();

        for ($i = 0; $i < 15; $i++) {
            Notification::factory()
                ->channel(DatabaseChannel::class)
                ->to('database')
                ->state([
                    'session_id' => UuidVO::generate()->getValue(),
                    'notifiable_type' => $this->user->getMorphClass(),
                    'notifiable_id' => $this->user->getKey(),
                    'message' => $message->toArray(),
                ])
                ->create();
        }

        $result = $this->user->latest_database_notifications;

        $this->assertCount(10, $result);
    }

    public function test_latest_database_notifications_returns_only_database_channel(): void
    {
        $this->createNotification();
        $this->createNotification();

        $message = $this->createMessage();
        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        $result = $this->user->latest_database_notifications;

        $this->assertCount(1, $result);
        $this->assertEquals(DatabaseChannel::class, $result->first()->channel);
    }

    public function test_latest_database_notifications_are_ordered_by_created_at_desc(): void
    {
        $message = $this->createMessage();

        $old = Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();
        $old->created_at = now()->subDays(2);
        $old->save();

        $recent = Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();
        $recent->created_at = now();
        $recent->save();

        $result = $this->user->latest_database_notifications;
        $items = $result->values();

        $this->assertEquals($recent->getId(), $items[0]->getId());
        $this->assertEquals($old->getId(), $items[1]->getId());
    }

    public function test_latest_database_notifications_are_scoped_to_user(): void
    {
        $otherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $message = $this->createMessage();

        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $otherUser->getMorphClass(),
                'notifiable_id' => $otherUser->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        $this->assertCount(1, $this->user->latest_database_notifications);
        $this->assertCount(1, $otherUser->latest_database_notifications);
    }

    // ============================================================================
    // Tests - unread_database_notifications_count
    // ============================================================================

    public function test_unread_database_notifications_count_returns_zero_when_none(): void
    {
        $this->assertEquals(0, $this->user->unread_database_notifications_count);
    }

    public function test_unread_database_notifications_count_returns_only_unread_database(): void
    {
        for ($i = 0; $i < 2; $i++) {
            Notification::factory()
                ->channel(DatabaseChannel::class)
                ->to('database')
                ->state([
                    'session_id' => UuidVO::generate()->getValue(),
                    'notifiable_type' => $this->user->getMorphClass(),
                    'notifiable_id' => $this->user->getKey(),
                    'message' => $this->createMessage()->toArray(),
                    'read_at' => null,
                ])
                ->create();
        }

        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $this->createMessage()->toArray(),
                'read_at' => now(),
            ])
            ->create();

        $this->createNotification();

        $this->assertEquals(2, $this->user->unread_database_notifications_count);
    }

    // ============================================================================
    // Tests - unread_database_notifications
    // ============================================================================

    public function test_unread_database_notifications_returns_empty_collection_when_none(): void
    {
        $result = $this->user->unread_database_notifications;

        $this->assertCount(0, $result);
    }

    public function test_unread_database_notifications_returns_only_unread_database(): void
    {
        for ($i = 0; $i < 2; $i++) {
            Notification::factory()
                ->channel(DatabaseChannel::class)
                ->to('database')
                ->state([
                    'session_id' => UuidVO::generate()->getValue(),
                    'notifiable_type' => $this->user->getMorphClass(),
                    'notifiable_id' => $this->user->getKey(),
                    'message' => $this->createMessage()->toArray(),
                    'read_at' => null,
                ])
                ->create();
        }

        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $this->createMessage()->toArray(),
                'read_at' => now(),
            ])
            ->create();

        $this->createNotification();

        $result = $this->user->unread_database_notifications;

        $this->assertCount(2, $result);
        foreach ($result as $notification) {
            $this->assertEquals(DatabaseChannel::class, $notification->channel);
            $this->assertFalse($notification->isRead());
        }
    }

    public function test_unread_database_notifications_are_scoped_to_user(): void
    {
        $otherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $message = $this->createMessage();

        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
                'read_at' => null,
            ])
            ->create();

        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $otherUser->getMorphClass(),
                'notifiable_id' => $otherUser->getKey(),
                'message' => $message->toArray(),
                'read_at' => null,
            ])
            ->create();

        $this->assertCount(1, $this->user->unread_database_notifications);
        $this->assertCount(1, $otherUser->unread_database_notifications);
    }

    // ============================================================================
    // Tests - has_unread_database_notifications
    // ============================================================================

    public function test_has_unread_database_notifications_returns_true(): void
    {
        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $this->createMessage()->toArray(),
                'read_at' => null,
            ])
            ->create();

        $this->assertTrue($this->user->has_unread_database_notifications);
    }

    public function test_has_unread_database_notifications_returns_false_when_all_read(): void
    {
        Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $this->createMessage()->toArray(),
                'read_at' => now(),
            ])
            ->create();

        $this->assertFalse($this->user->has_unread_database_notifications);
    }

    public function test_has_unread_database_notifications_returns_false_when_no_database_notifications(): void
    {
        $this->createNotification();

        $this->assertFalse($this->user->has_unread_database_notifications);
    }

    public function test_has_unread_database_notifications_returns_false_when_no_notifications(): void
    {
        $this->assertFalse($this->user->has_unread_database_notifications);
    }
}
