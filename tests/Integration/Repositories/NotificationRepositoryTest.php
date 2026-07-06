<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Repositories;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\Records\NotificationFilterRecord;
use AndyDefer\LaravelNotification\Records\NotificationRecord;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\UuidVO;
use AndyDefer\Repository\Enums\SortDirection;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\Records\PaginateRecord;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

final class NotificationRepositoryTest extends TestCase
{
    use DatabaseMigrations;

    private NotificationRepository $repository;

    private TestUser $user;

    private FqcnChannelVO $mailChannelVO;

    private FqcnChannelVO $databaseChannelVO;

    private FqcnChannelVO $smsChannelVO;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runDatabaseMigrations();

        DB::table('notifications')->delete();

        $this->repository = new NotificationRepository;
        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->mailChannelVO = new FqcnChannelVO(MailChannel::class);
        $this->databaseChannelVO = new FqcnChannelVO(DatabaseChannel::class);
        $this->smsChannelVO = new FqcnChannelVO(SmsChannel::class);
    }

    protected function tearDown(): void
    {
        DB::table('notifications')->delete();
        $this->user->delete();
        parent::tearDown();
    }

    private function generateSessionId(): UuidVO
    {
        return UuidVO::generate();
    }

    private function createMessage(
        string $body,
        string $subject,
        string $type = 'test',
        array $data = []
    ): NotificationMessageVO {
        return new NotificationMessageVO(
            body: new MessageBodyVO($body),
            subject: new MessageSubjectVO($subject),
            type: $type,
            data: ! empty($data) ? StrictDataObject::from($data) : null,
        );
    }

    private function createNotification(
        string $type,
        FqcnChannelVO $channel,
        string $destination = 'test@example.com',
        string $body = 'Test message',
        string $subject = 'Test Subject',
        NotificationStatus $status = NotificationStatus::PENDING,
        array $data = []
    ): Notification {
        $id = UuidVO::generate();
        $sessionId = $this->generateSessionId();
        $message = $this->createMessage($body, $subject, $type, $data);

        $record = new NotificationRecord(
            id: $id,
            session_id: $sessionId,
            channel: $channel,
            destination: $destination,
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
            status: $status,
        );

        return $this->repository->create($record);
    }

    public function test_create_notification(): void
    {
        // Arrange : Prepare notification data
        $id = UuidVO::generate();
        $sessionId = $this->generateSessionId();
        $message = $this->createMessage(
            body: 'Bienvenue',
            subject: 'Bienvenue sur notre plateforme',
            type: 'welcome',
            data: ['user_id' => 1]
        );

        $record = new NotificationRecord(
            id: $id,
            session_id: $sessionId,
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        // Act : Create the notification
        $model = $this->repository->create($record);

        // Assert : Verify the notification was created correctly
        $this->assertInstanceOf(Notification::class, $model);
        $this->assertEquals($id->getValue(), $model->getId());
        $this->assertDatabaseHas('notifications', [
            'id' => $id->getValue(),
            'session_id' => $sessionId->getValue(),
            'channel' => $this->mailChannelVO->getValue(),
            'destination' => 'test@example.com',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'status' => NotificationStatus::PENDING->value,
        ]);

        $this->assertEquals('Bienvenue', $model->getMessage()->getBodyValue());
        $this->assertEquals('Bienvenue sur notre plateforme', $model->getMessage()->getSubjectValue());
        $this->assertEquals('welcome', $model->getMessage()->getType());
        $this->assertEquals(1, $model->getMessage()->getData()->user_id);
    }

    public function test_create_notification_with_sent_status(): void
    {
        // Arrange : Prepare notification data with SENT status
        $id = UuidVO::generate();
        $sessionId = $this->generateSessionId();
        $message = $this->createMessage(
            body: 'Payment received',
            subject: 'Payment confirmation',
            type: 'payment'
        );

        $record = new NotificationRecord(
            id: $id,
            session_id: $sessionId,
            channel: $this->databaseChannelVO,
            destination: 'database',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
            status: NotificationStatus::SENT,
        );

        // Act : Create the notification
        $model = $this->repository->create($record);

        // Assert : Verify the notification was created with SENT status
        $this->assertDatabaseHas('notifications', [
            'id' => $id->getValue(),
            'session_id' => $sessionId->getValue(),
            'status' => NotificationStatus::SENT->value,
        ]);
    }

    // ==================== FIND ====================

    public function test_find_returns_notification(): void
    {
        // Arrange : Create a notification
        $id = UuidVO::generate();
        $sessionId = $this->generateSessionId();
        $message = $this->createMessage(body: 'Test', subject: 'Test Subject');

        $record = new NotificationRecord(
            id: $id,
            session_id: $sessionId,
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        $created = $this->repository->create($record);

        // Act : Find the notification
        $found = $this->repository->find($created->getId());

        // Assert : Verify the notification was found
        $this->assertNotNull($found);
        $this->assertEquals($created->getId(), $found->getId());
    }

    public function test_find_returns_null_when_not_found(): void
    {
        // Act : Try to find a non-existent notification
        $found = $this->repository->find('non-existent-uuid');

        // Assert : Verify null is returned
        $this->assertNull($found);
    }

    // ==================== FIND BY ====================

    public function test_find_by_with_filters(): void
    {
        // Arrange : Create notifications with different channels
        $this->createNotification('welcome', $this->mailChannelVO);
        $this->createNotification('welcome', $this->mailChannelVO);
        $this->createNotification('payment', $this->smsChannelVO);

        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
        ]);

        // Act : Find notifications by channel filter
        $results = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        // Assert : Verify only mail channel notifications are returned
        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals($this->mailChannelVO->getValue(), $result->channel);
        }
    }

    public function test_find_by_with_limit(): void
    {
        // Arrange : Create multiple notifications
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);

        $filter = NotificationFilterRecord::from([]);

        // Act : Find notifications with limit
        $results = $this->repository->findBy(
            new FindByRecord(
                filters: $filter,
                limit: 2
            )
        );

        // Assert : Verify only 2 results are returned
        $this->assertCount(2, $results);
    }

    public function test_find_by_with_notifiable_filter(): void
    {
        // Arrange : Create notifications for a user
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);

        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
        ]);

        // Act : Find notifications by notifiable filter
        $results = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        // Assert : Verify all notifications belong to the user
        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals($this->user->getMorphClass(), $result->notifiable_type);
            $this->assertEquals($this->user->getKey(), $result->notifiable_id);
        }
    }

    public function test_find_by_with_status_filter(): void
    {
        // Arrange : Create notifications with different statuses
        $this->createNotification('test', $this->mailChannelVO, 'test@example.com', 'Test', 'Test', NotificationStatus::SENT);
        $this->createNotification('test', $this->mailChannelVO, 'test@example.com', 'Test', 'Test', NotificationStatus::PENDING);

        $filter = NotificationFilterRecord::from([
            'status' => NotificationStatus::SENT,
        ]);

        // Act : Find notifications by status filter
        $results = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        // Assert : Verify only SENT notifications are returned
        $this->assertCount(1, $results);
        $this->assertEquals(NotificationStatus::SENT, $results->first()->getStatus());
    }

    public function test_find_by_with_read_filter(): void
    {
        // Arrange : Create read and unread notifications
        $sessionId1 = $this->generateSessionId();
        $message = $this->createMessage('Read', 'Read Subject');

        $record1 = new NotificationRecord(
            id: UuidVO::generate(),
            session_id: $sessionId1,
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );
        $read = $this->repository->create($record1);
        $this->repository->markAsRead($read->getId());

        $sessionId2 = $this->generateSessionId();
        $record2 = new NotificationRecord(
            id: UuidVO::generate(),
            session_id: $sessionId2,
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );
        $this->repository->create($record2);

        $filter = NotificationFilterRecord::from([
            'read' => true,
            'notifiable_id' => $this->user->getKey(),
        ]);

        // Act : Find read notifications
        $results = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        // Assert : Verify only read notifications are returned
        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->isRead());
    }

    public function test_find_by_with_destination_filter(): void
    {
        // Arrange : Create notifications with different destinations
        $this->createNotification('test', $this->mailChannelVO, 'test1@example.com');
        $this->createNotification('test', $this->mailChannelVO, 'test2@example.com');

        $filter = NotificationFilterRecord::from([
            'destination' => 'test1@example.com',
        ]);

        // Act : Find notifications by destination filter
        $results = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        // Assert : Verify only the correct destination is returned
        $this->assertCount(1, $results);
        $this->assertEquals('test1@example.com', $results->first()->getDestination());
    }

    // ==================== COUNT ====================

    public function test_count_by_criteria(): void
    {
        // Arrange : Create notifications with different channels
        $this->createNotification('welcome', $this->mailChannelVO);
        $this->createNotification('welcome', $this->mailChannelVO);
        $this->createNotification('payment', $this->smsChannelVO);

        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
        ]);

        // Act : Count notifications by criteria
        $count = $this->repository->count($filter);

        // Assert : Verify the count is correct
        $this->assertEquals(2, $count);
    }

    public function test_count_all_when_no_filters(): void
    {
        // Arrange : Create multiple notifications
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);

        // Act : Count all notifications
        $count = $this->repository->count();

        // Assert : Verify the count is correct
        $this->assertEquals(2, $count);
    }

    public function test_count_by_notifiable(): void
    {
        // Arrange : Create notifications for a user
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);

        // Act : Count notifications by notifiable
        $count = $this->repository->countByNotifiable($this->user);

        // Assert : Verify the count is correct
        $this->assertEquals(2, $count);
    }

    public function test_count_by_status(): void
    {
        // Arrange : Create notifications with different statuses
        $message = $this->createMessage('Test', 'Test Subject');

        $record1 = new NotificationRecord(
            id: UuidVO::generate(),
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );
        $this->repository->create($record1);

        $record2 = new NotificationRecord(
            id: UuidVO::generate(),
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
            status: NotificationStatus::SENT,
        );
        $this->repository->create($record2);

        // Act : Count by status
        $pendingCount = $this->repository->countByStatus($this->user, NotificationStatus::PENDING);
        $sentCount = $this->repository->countByStatus($this->user, NotificationStatus::SENT);

        // Assert : Verify counts are correct
        $this->assertEquals(1, $pendingCount);
        $this->assertEquals(1, $sentCount);
    }

    // ==================== EXISTS ====================

    public function test_exists_returns_true_when_found(): void
    {
        // Arrange : Create a notification
        $this->createNotification('test', $this->mailChannelVO);

        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
            'notifiable_id' => $this->user->getKey(),
        ]);

        // Act : Check if notification exists
        $exists = $this->repository->exists($filter);

        // Assert : Verify notification exists
        $this->assertTrue($exists);
    }

    public function test_exists_returns_false_when_not_found(): void
    {
        // Arrange : Create a filter for a non-existent notification
        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
            'notifiable_id' => 99999,
        ]);

        // Act : Check if notification exists
        $exists = $this->repository->exists($filter);

        // Assert : Verify notification does not exist
        $this->assertFalse($exists);
    }

    // ==================== UPDATE ====================

    public function test_update_notification(): void
    {
        // Arrange : Create a notification
        $id = UuidVO::generate();
        $message = $this->createMessage('Original', 'Original Subject', 'original');
        $record = new NotificationRecord(
            id: $id,
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        $model = $this->repository->create($record);

        // Arrange : Prepare updated data
        $newMessage = $this->createMessage('Updated', 'Updated Subject', 'updated');
        $updateRecord = new NotificationRecord(
            message: $newMessage,
            status: NotificationStatus::SENT,
        );

        // Act : Update the notification
        $updated = $this->repository->update($model->getId(), $updateRecord);

        // Assert : Verify the notification was updated
        $this->assertEquals('Updated', $updated->getMessage()->getBodyValue());
        $this->assertEquals('Updated Subject', $updated->getMessage()->getSubjectValue());
        $this->assertEquals('updated', $updated->getMessage()->getType());
        $this->assertEquals(NotificationStatus::SENT, $updated->getStatus());
    }

    // ==================== MARK AS ====================

    public function test_mark_as_read(): void
    {
        // Arrange : Create a notification
        $id = UuidVO::generate();
        $message = $this->createMessage('Test', 'Test Subject');
        $record = new NotificationRecord(
            id: $id,
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        $model = $this->repository->create($record);

        // Assert : Initially not read
        $this->assertNull($model->getReadAt());
        $this->assertFalse($model->isRead());

        // Act : Mark as read
        $result = $this->repository->markAsRead($model->getId());

        // Assert : Verify marked as read
        $this->assertTrue($result);
        $updated = $this->repository->find($model->getId());
        $this->assertNotNull($updated->getReadAt());
        $this->assertTrue($updated->isRead());
    }

    public function test_mark_as_delivered(): void
    {
        // Arrange : Create a notification
        $id = UuidVO::generate();
        $message = $this->createMessage('Test', 'Test Subject');
        $record = new NotificationRecord(
            id: $id,
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        $model = $this->repository->create($record);

        // Assert : Initially PENDING
        $this->assertEquals(NotificationStatus::PENDING, $model->getStatus());

        // Act : Mark as delivered
        $result = $this->repository->markAsDelivered($model->getId());

        // Assert : Verify marked as delivered
        $this->assertTrue($result);
        $updated = $this->repository->find($model->getId());
        $this->assertEquals(NotificationStatus::DELIVERED, $updated->getStatus());
    }

    public function test_mark_as_sent(): void
    {
        // Arrange : Create a notification
        $id = UuidVO::generate();
        $message = $this->createMessage('Test', 'Test Subject');
        $record = new NotificationRecord(
            id: $id,
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        $model = $this->repository->create($record);

        // Assert : Initially not sent
        $this->assertEquals(NotificationStatus::PENDING, $model->getStatus());
        $this->assertNull($model->getSentAt());

        // Act : Mark as sent
        $result = $this->repository->markAsSent($model->getId());

        // Assert : Verify marked as sent
        $this->assertTrue($result);
        $updated = $this->repository->find($model->getId());
        $this->assertEquals(NotificationStatus::SENT, $updated->getStatus());
        $this->assertNotNull($updated->getSentAt());
    }

    public function test_mark_as_failed(): void
    {
        // Arrange : Create a notification
        $id = UuidVO::generate();
        $message = $this->createMessage('Test', 'Test Subject');
        $record = new NotificationRecord(
            id: $id,
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        $model = $this->repository->create($record);

        // Assert : Initially not failed
        $this->assertEquals(NotificationStatus::PENDING, $model->getStatus());
        $this->assertNull($model->getError());

        $error = 'Mail delivery failed';

        // Act : Mark as failed
        $result = $this->repository->markAsFailed($model->getId(), $error);

        // Assert : Verify marked as failed
        $this->assertTrue($result);
        $updated = $this->repository->find($model->getId());
        $this->assertEquals(NotificationStatus::FAILED, $updated->getStatus());
        $this->assertEquals($error, $updated->getError());
    }

    public function test_mark_as_read_by_session(): void
    {
        // Arrange : Create multiple notifications in same session
        $sessionId = $this->generateSessionId();
        $message = $this->createMessage('Test', 'Test Subject');

        $record1 = new NotificationRecord(
            id: UuidVO::generate(),
            session_id: $sessionId,
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );
        $this->repository->create($record1);

        $record2 = new NotificationRecord(
            id: UuidVO::generate(),
            session_id: $sessionId,
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );
        $this->repository->create($record2);

        // Act : Mark all notifications in session as read
        $count = $this->repository->markAsReadBySession($sessionId->getValue());

        // Assert : Verify all notifications were marked as read
        $this->assertEquals(2, $count);

        $notifications = Notification::where('session_id', $sessionId->getValue())->get();
        foreach ($notifications as $notification) {
            $this->assertNotNull($notification->getReadAt());
            $this->assertTrue($notification->isRead());
        }
    }

    // ==================== DELETE ====================

    public function test_delete_notification(): void
    {
        // Arrange : Create a notification
        $id = UuidVO::generate();
        $message = $this->createMessage('Test', 'Test Subject');
        $record = new NotificationRecord(
            id: $id,
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        $model = $this->repository->create($record);
        $this->assertDatabaseHas('notifications', ['id' => $id->getValue()]);

        // Act : Delete the notification
        $deleted = $this->repository->delete($model->getId());

        // Assert : Verify the notification was soft deleted
        $this->assertTrue($deleted);
        $this->assertSoftDeleted('notifications', ['id' => $id->getValue()]);
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        // Act : Try to delete a non-existent notification
        $deleted = $this->repository->delete('non-existent-uuid');

        // Assert : Verify false is returned
        $this->assertFalse($deleted);
    }

    public function test_delete_bulk(): void
    {
        // Arrange : Create multiple notifications
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('other', $this->smsChannelVO);

        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
        ]);

        // Act : Delete notifications in bulk
        $deleted = $this->repository->deleteBulk($filter);

        // Assert : Verify the correct number were deleted
        $this->assertEquals(2, $deleted);

        $remaining = $this->repository->count();
        $this->assertEquals(1, $remaining);
    }

    // ==================== RESTORE ====================

    public function test_restore_soft_deleted_notification(): void
    {
        // Arrange : Create and soft delete a notification
        $id = UuidVO::generate();
        $message = $this->createMessage('Test', 'Test Subject');
        $record = new NotificationRecord(
            id: $id,
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        $model = $this->repository->create($record);
        $this->repository->delete($model->getId());

        $this->assertSoftDeleted('notifications', ['id' => $id->getValue()]);

        // Act : Restore the notification
        $restored = $this->repository->restore($model->getId());

        // Assert : Verify the notification was restored
        $this->assertTrue($restored);
        $this->assertDatabaseHas('notifications', [
            'id' => $id->getValue(),
            'deleted_at' => null,
        ]);
    }

    public function test_restore_returns_false_when_not_found(): void
    {
        // Act : Try to restore a non-existent notification
        $restored = $this->repository->restore('non-existent-uuid');

        // Assert : Verify false is returned
        $this->assertFalse($restored);
    }

    // ==================== FORCE DELETE ====================

    public function test_force_delete_permanently_removes_notification(): void
    {
        // Arrange : Create a notification
        $id = UuidVO::generate();
        $message = $this->createMessage('Test', 'Test Subject');
        $record = new NotificationRecord(
            id: $id,
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        $model = $this->repository->create($record);
        $this->assertDatabaseHas('notifications', ['id' => $id->getValue()]);

        // Act : Force delete the notification
        $deleted = $this->repository->forceDelete($model->getId());

        // Assert : Verify the notification was permanently removed
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('notifications', ['id' => $id->getValue()]);
    }

    public function test_force_delete_bulk(): void
    {
        // Arrange : Create multiple notifications
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);

        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
        ]);

        // Act : Force delete notifications in bulk
        $deleted = $this->repository->forceDeleteBulk($filter);

        // Assert : Verify all notifications were permanently removed
        $this->assertEquals(2, $deleted);

        $remaining = $this->repository->count();
        $this->assertEquals(0, $remaining);
    }

    // ==================== FIND WITH TRASHED ====================

    public function test_find_with_trashed_returns_soft_deleted(): void
    {
        // Arrange : Create and soft delete a notification
        $id = UuidVO::generate();
        $message = $this->createMessage('Test', 'Test Subject');
        $record = new NotificationRecord(
            id: $id,
            session_id: $this->generateSessionId(),
            channel: $this->mailChannelVO,
            destination: 'test@example.com',
            notifiable_type: $this->user->getMorphClass(),
            notifiable_id: $this->user->getKey(),
            message: $message,
        );

        $model = $this->repository->create($record);
        $this->repository->delete($model->getId());

        // Act : Find the soft deleted notification
        $found = $this->repository->findWithTrashed($model->getId());

        // Assert : Verify the soft deleted notification is found
        $this->assertNotNull($found);
        $this->assertEquals($id->getValue(), $found->getId());
        $this->assertNotNull($found->getDeletedAt());
    }

    // ==================== PAGINATE ====================

    public function test_paginate_returns_paginated_results(): void
    {
        // Arrange : Create multiple notifications
        for ($i = 0; $i < 15; $i++) {
            $this->createNotification('test', $this->mailChannelVO);
        }

        $filter = NotificationFilterRecord::from([]);

        // Act : Paginate the results
        $results = $this->repository->paginate(
            new PaginateRecord(
                filters: $filter,
                perPage: 5,
                page: 1
            )
        );

        // Assert : Verify pagination is correct
        $this->assertEquals(15, $results->total());
        $this->assertEquals(5, $results->perPage());
        $this->assertEquals(1, $results->currentPage());
        $this->assertCount(5, $results->items());
    }

    public function test_paginate_with_sorting(): void
    {
        // Arrange : Create notifications with different channels
        $this->createNotification('aaa', $this->mailChannelVO);
        $this->createNotification('zzz', $this->mailChannelVO);
        $this->createNotification('mmm', $this->mailChannelVO);

        $filter = NotificationFilterRecord::from([]);

        // Act : Paginate with sorting
        $results = $this->repository->paginate(
            new PaginateRecord(
                filters: $filter,
                perPage: 10,
                page: 1,
                sortBy: 'channel',
                sortDir: SortDirection::ASC
            )
        );

        // Assert : Verify sorting is correct
        $items = $results->items();
        $this->assertEquals(MailChannel::class, $items[0]->channel);
        $this->assertEquals(MailChannel::class, $items[1]->channel);
        $this->assertEquals(MailChannel::class, $items[2]->channel);
    }
}
