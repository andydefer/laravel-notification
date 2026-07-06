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

        $model = $this->repository->create($record);

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

        $model = $this->repository->create($record);

        $this->assertDatabaseHas('notifications', [
            'id' => $id->getValue(),
            'session_id' => $sessionId->getValue(),
            'status' => NotificationStatus::SENT->value,
        ]);
    }

    // ==================== FIND ====================

    public function test_find_returns_notification(): void
    {
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
        $found = $this->repository->find($created->getId());

        $this->assertNotNull($found);
        $this->assertEquals($created->getId(), $found->getId());
    }

    public function test_find_returns_null_when_not_found(): void
    {
        $found = $this->repository->find('non-existent-uuid');
        $this->assertNull($found);
    }

    // ==================== FIND BY ====================

    public function test_find_by_with_filters(): void
    {
        $this->createNotification('welcome', $this->mailChannelVO);
        $this->createNotification('welcome', $this->mailChannelVO);
        $this->createNotification('payment', $this->smsChannelVO);

        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
        ]);

        $results = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals($this->mailChannelVO->getValue(), $result->channel);
        }
    }

    public function test_find_by_with_limit(): void
    {
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);

        $filter = NotificationFilterRecord::from([]);

        $results = $this->repository->findBy(
            new FindByRecord(
                filters: $filter,
                limit: 2
            )
        );

        $this->assertCount(2, $results);
    }

    public function test_find_by_with_notifiable_filter(): void
    {
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);

        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
        ]);

        $results = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertEquals($this->user->getMorphClass(), $result->notifiable_type);
            $this->assertEquals($this->user->getKey(), $result->notifiable_id);
        }
    }

    public function test_find_by_with_status_filter(): void
    {
        $this->createNotification('test', $this->mailChannelVO, 'test@example.com', 'Test', 'Test', NotificationStatus::SENT);
        $this->createNotification('test', $this->mailChannelVO, 'test@example.com', 'Test', 'Test', NotificationStatus::PENDING);

        $filter = NotificationFilterRecord::from([
            'status' => NotificationStatus::SENT,
        ]);

        $results = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $this->assertCount(1, $results);
        $this->assertEquals(NotificationStatus::SENT, $results->first()->getStatus());
    }

    public function test_find_by_with_read_filter(): void
    {
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

        $results = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->isRead());
    }

    public function test_find_by_with_destination_filter(): void
    {
        $this->createNotification('test', $this->mailChannelVO, 'test1@example.com');
        $this->createNotification('test', $this->mailChannelVO, 'test2@example.com');

        $filter = NotificationFilterRecord::from([
            'destination' => 'test1@example.com',
        ]);

        $results = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $this->assertCount(1, $results);
        $this->assertEquals('test1@example.com', $results->first()->getDestination());
    }

    // ==================== COUNT ====================

    public function test_count_by_criteria(): void
    {
        $this->createNotification('welcome', $this->mailChannelVO);
        $this->createNotification('welcome', $this->mailChannelVO);
        $this->createNotification('payment', $this->smsChannelVO);

        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
        ]);

        $count = $this->repository->count($filter);
        $this->assertEquals(2, $count);
    }

    public function test_count_all_when_no_filters(): void
    {
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);

        $count = $this->repository->count();
        $this->assertEquals(2, $count);
    }

    public function test_count_by_notifiable(): void
    {
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);

        $count = $this->repository->countByNotifiable($this->user);
        $this->assertEquals(2, $count);
    }

    public function test_count_by_status(): void
    {
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

        $pendingCount = $this->repository->countByStatus($this->user, NotificationStatus::PENDING);
        $sentCount = $this->repository->countByStatus($this->user, NotificationStatus::SENT);

        $this->assertEquals(1, $pendingCount);
        $this->assertEquals(1, $sentCount);
    }

    // ==================== EXISTS ====================

    public function test_exists_returns_true_when_found(): void
    {
        $this->createNotification('test', $this->mailChannelVO);

        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
            'notifiable_id' => $this->user->getKey(),
        ]);

        $this->assertTrue($this->repository->exists($filter));
    }

    public function test_exists_returns_false_when_not_found(): void
    {
        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
            'notifiable_id' => 99999,
        ]);

        $this->assertFalse($this->repository->exists($filter));
    }

    // ==================== UPDATE ====================

    public function test_update_notification(): void
    {
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

        $newMessage = $this->createMessage('Updated', 'Updated Subject', 'updated');
        $updateRecord = new NotificationRecord(
            message: $newMessage,
            status: NotificationStatus::SENT,
        );

        $updated = $this->repository->update($model->getId(), $updateRecord);

        $this->assertEquals('Updated', $updated->getMessage()->getBodyValue());
        $this->assertEquals('Updated Subject', $updated->getMessage()->getSubjectValue());
        $this->assertEquals('updated', $updated->getMessage()->getType());
        $this->assertEquals(NotificationStatus::SENT, $updated->getStatus());
    }

    // ==================== MARK AS ====================

    public function test_mark_as_read(): void
    {
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

        $this->assertNull($model->getReadAt());
        $this->assertFalse($model->isRead());

        $result = $this->repository->markAsRead($model->getId());
        $this->assertTrue($result);

        $updated = $this->repository->find($model->getId());
        $this->assertNotNull($updated->getReadAt());
        $this->assertTrue($updated->isRead());
    }

    public function test_mark_as_delivered(): void
    {
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

        $this->assertEquals(NotificationStatus::PENDING, $model->getStatus());

        $result = $this->repository->markAsDelivered($model->getId());
        $this->assertTrue($result);

        $updated = $this->repository->find($model->getId());
        $this->assertEquals(NotificationStatus::DELIVERED, $updated->getStatus());
    }

    public function test_mark_as_sent(): void
    {
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

        $this->assertEquals(NotificationStatus::PENDING, $model->getStatus());
        $this->assertNull($model->getSentAt());

        $result = $this->repository->markAsSent($model->getId());
        $this->assertTrue($result);

        $updated = $this->repository->find($model->getId());
        $this->assertEquals(NotificationStatus::SENT, $updated->getStatus());
        $this->assertNotNull($updated->getSentAt());
    }

    public function test_mark_as_failed(): void
    {
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

        $this->assertEquals(NotificationStatus::PENDING, $model->getStatus());
        $this->assertNull($model->getError());

        $error = 'Mail delivery failed';
        $result = $this->repository->markAsFailed($model->getId(), $error);
        $this->assertTrue($result);

        $updated = $this->repository->find($model->getId());
        $this->assertEquals(NotificationStatus::FAILED, $updated->getStatus());
        $this->assertEquals($error, $updated->getError());
    }

    public function test_mark_as_read_by_session(): void
    {
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

        $count = $this->repository->markAsReadBySession($sessionId->getValue());
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

        $deleted = $this->repository->delete($model->getId());
        $this->assertTrue($deleted);

        $this->assertSoftDeleted('notifications', ['id' => $id->getValue()]);
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        $deleted = $this->repository->delete('non-existent-uuid');
        $this->assertFalse($deleted);
    }

    public function test_delete_bulk(): void
    {
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('other', $this->smsChannelVO);

        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
        ]);

        $deleted = $this->repository->deleteBulk($filter);
        $this->assertEquals(2, $deleted);

        $remaining = $this->repository->count();
        $this->assertEquals(1, $remaining);
    }

    // ==================== RESTORE ====================

    public function test_restore_soft_deleted_notification(): void
    {
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

        $restored = $this->repository->restore($model->getId());
        $this->assertTrue($restored);

        $this->assertDatabaseHas('notifications', [
            'id' => $id->getValue(),
            'deleted_at' => null,
        ]);
    }

    public function test_restore_returns_false_when_not_found(): void
    {
        $restored = $this->repository->restore('non-existent-uuid');
        $this->assertFalse($restored);
    }

    // ==================== FORCE DELETE ====================

    public function test_force_delete_permanently_removes_notification(): void
    {
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

        $deleted = $this->repository->forceDelete($model->getId());
        $this->assertTrue($deleted);

        $this->assertDatabaseMissing('notifications', ['id' => $id->getValue()]);
    }

    public function test_force_delete_bulk(): void
    {
        $this->createNotification('test', $this->mailChannelVO);
        $this->createNotification('test', $this->mailChannelVO);

        $filter = NotificationFilterRecord::from([
            'channel' => $this->mailChannelVO,
        ]);

        $deleted = $this->repository->forceDeleteBulk($filter);
        $this->assertEquals(2, $deleted);

        $remaining = $this->repository->count();
        $this->assertEquals(0, $remaining);
    }

    // ==================== FIND WITH TRASHED ====================

    public function test_find_with_trashed_returns_soft_deleted(): void
    {
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

        $found = $this->repository->findWithTrashed($model->getId());
        $this->assertNotNull($found);
        $this->assertEquals($id->getValue(), $found->getId());
        $this->assertNotNull($found->getDeletedAt());
    }

    // ==================== PAGINATE ====================

    public function test_paginate_returns_paginated_results(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->createNotification('test', $this->mailChannelVO);
        }

        $filter = NotificationFilterRecord::from([]);

        $results = $this->repository->paginate(
            new PaginateRecord(
                filters: $filter,
                perPage: 5,
                page: 1
            )
        );

        $this->assertEquals(15, $results->total());
        $this->assertEquals(5, $results->perPage());
        $this->assertEquals(1, $results->currentPage());
        $this->assertCount(5, $results->items());
    }

    public function test_paginate_with_sorting(): void
    {
        $this->createNotification('aaa', $this->mailChannelVO);
        $this->createNotification('zzz', $this->mailChannelVO);
        $this->createNotification('mmm', $this->mailChannelVO);

        $filter = NotificationFilterRecord::from([]);

        $results = $this->repository->paginate(
            new PaginateRecord(
                filters: $filter,
                perPage: 10,
                page: 1,
                sortBy: 'channel',
                sortDir: SortDirection::ASC
            )
        );

        $items = $results->items();
        $this->assertEquals(MailChannel::class, $items[0]->channel);
        $this->assertEquals(MailChannel::class, $items[1]->channel);
        $this->assertEquals(MailChannel::class, $items[2]->channel);
    }
}
