<?php

// tests/Integration/Traits/HasNotificationsTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Traits;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\Notification;
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

        // Créer une notification pour l'autre utilisateur
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
        // 1. Vérifier qu'il n'y a pas de notifications
        $this->assertFalse($this->user->has_notifications);
        $this->assertEquals(0, $this->user->unread_notifications_count);

        // 2. Créer des notifications
        $n1 = $this->createNotification(status: NotificationStatus::PENDING, read: false);
        $n2 = $this->createNotification(status: NotificationStatus::SENT, read: false);
        $n3 = $this->createNotification(status: NotificationStatus::SENT, read: true);

        // 3. Vérifier l'état
        $this->assertTrue($this->user->has_notifications);
        $this->assertEquals(2, $this->user->unread_notifications_count);
        $this->assertTrue($this->user->has_unread_notifications);

        // 4. Marquer une notification comme lue
        $this->user->markNotificationAsRead($n1->getId());
        $this->assertEquals(1, $this->user->unread_notifications_count);

        // 5. Récupérer les notifications non lues
        $this->assertCount(1, $this->user->unread_notifications);

        // 6. Récupérer les notifications lues
        $this->assertCount(2, $this->user->read_notifications);

        // 7. Compter par statut
        $this->assertEquals(1, $this->user->countNotificationsByStatus(NotificationStatus::PENDING));
        $this->assertEquals(2, $this->user->countNotificationsByStatus(NotificationStatus::SENT));

        // 8. Marquer tout comme lu
        $count = $this->user->markAllNotificationsAsRead();
        $this->assertEquals(1, $count);
        $this->assertEquals(0, $this->user->unread_notifications_count);

        // 9. Supprimer les notifications lues
        $deleted = $this->user->deleteReadNotifications();
        $this->assertEquals(3, $deleted);
        $this->assertEquals(0, $this->user->notifications()->count());
        $this->assertFalse($this->user->has_notifications);
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
        // Arrange : Create notifications with different channels
        $this->createNotification(); // MailChannel (default dans createNotification)
        $this->createNotification(); // MailChannel
        $this->createNotification(); // MailChannel

        // Create a notification with a different channel
        $message = $this->createMessage();
        $notification = Notification::factory()
            ->channel(DatabaseChannel::class)
            ->to('database')
            ->state([
                'session_id' => UuidVO::generate()->getValue(),
                'notifiable_type' => $this->user->getMorphClass(),
                'notifiable_id' => $this->user->getKey(),
                'message' => $message->toArray(),
            ])
            ->create();

        // Act : Get notifications by channel
        $mailNotifications = $this->user->notificationsByChannel(MailChannel::class);
        $databaseNotifications = $this->user->notificationsByChannel(DatabaseChannel::class);

        // Assert : Verify only matching channel notifications are returned
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
        // Arrange : Create 15 mail notifications
        for ($i = 0; $i < 15; $i++) {
            $this->createNotification();
        }

        // Act : Get notifications with limit
        $result = $this->user->notificationsByChannel(MailChannel::class, 5);

        // Assert : Verify limit is respected
        $this->assertCount(5, $result);
    }

    public function test_notifications_by_channel_default_limit_is_ten(): void
    {
        // Arrange : Create 15 mail notifications
        for ($i = 0; $i < 15; $i++) {
            $this->createNotification();
        }

        // Act : Get notifications without specifying limit
        $result = $this->user->notificationsByChannel(MailChannel::class);

        // Assert : Verify default limit of 10 is applied
        $this->assertCount(10, $result);
    }

    public function test_notifications_by_channel_are_scoped_to_user(): void
    {
        // Arrange : Create another user with notifications
        $otherUser = TestUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $this->createNotification();
        $this->createNotification();

        // Create a notification for the other user with same channel
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

        // Act : Get notifications by channel for each user
        $userNotifications = $this->user->notificationsByChannel(MailChannel::class);
        $otherUserNotifications = $otherUser->notificationsByChannel(MailChannel::class);

        // Assert : Verify isolation between users
        $this->assertCount(2, $userNotifications);
        $this->assertCount(1, $otherUserNotifications);
    }

    public function test_notifications_by_channel_returns_ordered_by_created_at_desc(): void
    {
        // Arrange : Create notifications with distinct timestamps
        $old = $this->createNotification();
        $old->created_at = now()->subDays(2);
        $old->save();

        $recent = $this->createNotification();
        $recent->created_at = now();
        $recent->save();

        $middle = $this->createNotification();
        $middle->created_at = now()->subDay();
        $middle->save();

        // Act : Get notifications by channel
        $result = $this->user->notificationsByChannel(MailChannel::class);

        // Assert : Verify ordering by created_at DESC
        $items = $result->values();
        $this->assertEquals($recent->getId(), $items[0]->getId());
        $this->assertEquals($middle->getId(), $items[1]->getId());
        $this->assertEquals($old->getId(), $items[2]->getId());
    }

    public function test_notifications_by_channel_with_limit_higher_than_count(): void
    {
        // Arrange : Create 3 notifications
        $this->createNotification();
        $this->createNotification();
        $this->createNotification();

        // Act : Get with a limit higher than the count
        $result = $this->user->notificationsByChannel(MailChannel::class, 100);

        // Assert : Verify all notifications are returned
        $this->assertCount(3, $result);
    }

    public function test_notifications_by_channel_with_different_channel_returns_empty(): void
    {
        // Arrange : Create only MailChannel notifications
        $this->createNotification();
        $this->createNotification();

        // Act : Try to get notifications from a different channel
        $result = $this->user->notificationsByChannel(SmsChannel::class);

        // Assert : Verify empty collection is returned
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
        // Arrange : Create mail notifications and database notifications
        $this->createNotification(); // MailChannel
        $this->createNotification(); // MailChannel

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

        // Act
        $result = $this->user->database_notifications;

        // Assert
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

        // Notification for user
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

        // Notification for other user
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

        // Create 15 database notifications
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
        // Arrange : Create mail notifications
        $this->createNotification();
        $this->createNotification();

        // Arrange : Create database notifications
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

        // Notification for user
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

        // Notification for other user
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
}
