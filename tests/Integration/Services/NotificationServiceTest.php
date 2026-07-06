<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Services;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\NotificationFilterRecord;
use AndyDefer\LaravelNotification\Records\SendAtRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\Records\SessionStatsRecord;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestEmptyChannel;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationStatsVO;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Repositories\RecurringTaskRepository;
use AndyDefer\Task\Repositories\UniqueTaskRepository;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

final class NotificationServiceTest extends TestCase
{
    use DatabaseMigrations;

    private NotificationServiceInterface $service;

    private TestUser $user;

    private NotificationMessageVO $message;

    private NotificationRepository $repository;

    private UniqueTaskRepository $uniqueTaskRepository;

    private RecurringTaskRepository $recurringTaskRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runDatabaseMigrations();

        $this->repository = app(NotificationRepository::class);
        $this->uniqueTaskRepository = app(UniqueTaskRepository::class);
        $this->recurringTaskRepository = app(RecurringTaskRepository::class);

        $this->service = new NotificationService(
            notificationRepository: $this->repository,
            senderProcessor: app(NotificationSenderProcessor::class),
            uniqueTaskService: app(UniqueTaskServiceInterface::class),
            recurringTaskService: app(RecurringTaskServiceInterface::class),
            logger: app(LoggerInterface::class),
            hydration: app(HydrationService::class),
        );

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+33123456789',
        ]);

        $this->message = new NotificationMessageVO(
            body: new MessageBodyVO('Test message'),
            subject: new MessageSubjectVO('Test Subject'),
            type: 'test',
            data: new StrictDataObject(['key' => 'value'])
        );
    }

    protected function tearDown(): void
    {
        $this->user->delete();
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    // ==================== TESTS: sendNow ====================

    public function test_send_now_with_all_channels(): void
    {
        $record = new SendNowRecord;

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(4, $results);

        foreach ($results as $result) {
            $this->assertTrue($result->success);
        }

        $count = $this->repository->countByNotifiable($this->user);
        $this->assertEquals(4, $count);
    }

    public function test_send_now_with_specific_channels(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendNowRecord(
            channels: $channels
        );

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);

        $result = $results->first();
        $this->assertTrue($result->success);
        $this->assertEquals(MailChannel::class, $result->channel->getValue());

        $count = $this->repository->countByNotifiable($this->user);
        $this->assertEquals(1, $count);
    }

    public function test_send_now_with_limit_per_channel(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendNowRecord(
            channels: $channels,
            limit_per_channel: 1
        );

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function test_send_now_with_empty_channels(): void
    {
        $channels = new FqcnChannelCollection;

        $record = new SendNowRecord(
            channels: $channels
        );

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(4, $results);
    }

    public function test_send_now_throws_exception_when_no_channels_available(): void
    {
        $user = TestEmptyChannel::create(['name' => 'No Channels']);

        $record = new SendNowRecord;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No available channels for notifiable');

        $this->service->sendNow($user, $this->message, $record);
    }

    // ==================== TESTS: sendLater ====================

    public function test_send_later_schedules_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendLaterRecord(
            delay_seconds: 300
        );

        $alias = $this->service->sendLater($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(SendDelayedNotificationTask::class, $task->getFqcn());
    }

    public function test_send_later_with_channels(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendLaterRecord(
            delay_seconds: 300,
            channels: $channels
        );

        $alias = $this->service->sendLater($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();
        $this->assertContains(MailChannel::class, $payload->get('channels'));
    }

    public function test_send_later_with_limit_per_channel(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendLaterRecord(
            delay_seconds: 300,
            limit_per_channel: 2
        );

        $alias = $this->service->sendLater($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();
        $this->assertEquals(2, $payload->get('limit_per_channel'));
    }

    public function test_send_later_throws_exception_when_delay_zero(): void
    {
        $record = new SendLaterRecord(
            delay_seconds: 0
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Delay seconds must be greater than 0.');

        $this->service->sendLater($this->user, $this->message, $record);
    }

    public function test_send_later_throws_exception_when_delay_negative(): void
    {
        $record = new SendLaterRecord(
            delay_seconds: -5
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Delay seconds must be greater than 0.');

        $this->service->sendLater($this->user, $this->message, $record);
    }

    // ==================== TESTS: sendAt ====================

    public function test_send_at_schedules_task_at_specific_time(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $scheduledAt = new NotificationDateTimeVO($frozenNow->copy()->addHours(2)->toIso8601String());

        $record = new SendAtRecord(
            scheduled_at: $scheduledAt
        );

        $alias = $this->service->sendAt($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        // Le service convertit NotificationDateTimeVO en Iso8601DateTimeVO pour la tâche
        $this->assertEquals($frozenNow->copy()->addHours(2)->toIso8601String(), $task->getScheduledAt()->getValue());
    }

    public function test_send_at_with_channels(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $scheduledAt = new NotificationDateTimeVO($frozenNow->copy()->addHours(2)->toIso8601String());

        $record = new SendAtRecord(
            scheduled_at: $scheduledAt,
            channels: $channels
        );

        $alias = $this->service->sendAt($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();
        $this->assertContains(MailChannel::class, $payload->get('channels'));
    }

    public function test_send_at_throws_exception_when_date_in_past(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $scheduledAt = new NotificationDateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String());

        $record = new SendAtRecord(
            scheduled_at: $scheduledAt
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scheduled date must be in the future.');

        $this->service->sendAt($this->user, $this->message, $record);
    }

    public function test_send_at_throws_exception_when_date_now(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $scheduledAt = new NotificationDateTimeVO($frozenNow->toIso8601String());

        $record = new SendAtRecord(
            scheduled_at: $scheduledAt
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scheduled date must be in the future.');

        $this->service->sendAt($this->user, $this->message, $record);
    }

    // ==================== TESTS: sendRecurring ====================

    public function test_send_recurring_schedules_recurring_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(SendRecurringNotificationTask::class, $task->getFqcn());
        $this->assertEquals(3600, $task->getIntervalSeconds()->getValue());
    }

    public function test_send_recurring_with_channels(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String()),
            channels: $channels
        );

        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $payload = $task->getPayload();
        $this->assertContains(MailChannel::class, $payload->get('channels'));
    }

    public function test_send_recurring_with_end_at(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $endAt = new NotificationDateTimeVO($frozenNow->copy()->addDays(7)->toIso8601String());

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String()),
            end_at: $endAt
        );

        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals($endAt->getValue(), $task->getEndAt()?->getValue());
    }

    public function test_send_recurring_with_limit_per_channel(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String()),
            limit_per_channel: 2
        );

        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $payload = $task->getPayload();
        $this->assertEquals(2, $payload->get('limit_per_channel'));
    }

    public function test_send_recurring_with_max_attempts(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String()),
            max_attempts: new MaxFailedAttemptsVO(5)
        );

        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(5, $task->getMaxFailedAttempts()->getValue());
    }

    public function test_send_recurring_throws_exception_when_interval_zero(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 0,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interval seconds must be at least 1 second.');

        $this->service->sendRecurring($this->user, $this->message, $record);
    }

    public function test_send_recurring_throws_exception_when_interval_negative(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: -1,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interval seconds must be at least 1 second.');

        $this->service->sendRecurring($this->user, $this->message, $record);
    }

    // ==================== TESTS: Task Management ====================

    public function test_cancel_unique_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendLaterRecord(delay_seconds: 300);
        $alias = $this->service->sendLater($this->user, $this->message, $record);

        $result = $this->service->cancel($alias->getValue());

        $this->assertTrue($result);
    }

    public function test_cancel_recurring_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $result = $this->service->cancel($alias->getValue());

        $this->assertTrue($result);
    }

    public function test_cancel_non_existing_task_returns_false(): void
    {
        $result = $this->service->cancel('unique@'.Uuid::uuid4()->toString());

        $this->assertFalse($result);
    }

    public function test_pause_recurring_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $result = $this->service->pause($alias->getValue());

        $this->assertTrue($result);
    }

    public function test_pause_non_existing_task_returns_false(): void
    {
        $result = $this->service->pause('recurring@'.Uuid::uuid4()->toString());

        $this->assertFalse($result);
    }

    public function test_resume_recurring_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->service->pause($alias->getValue());
        $result = $this->service->resume($alias->getValue());

        $this->assertTrue($result);
    }

    public function test_resume_non_existing_task_returns_false(): void
    {
        $result = $this->service->resume('recurring@'.Uuid::uuid4()->toString());

        $this->assertFalse($result);
    }

    public function test_change_interval_recurring_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $result = $this->service->changeInterval($alias->getValue(), 7200);

        $this->assertTrue($result);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(7200, $task->getIntervalSeconds()->getValue());
    }

    public function test_change_interval_non_existing_task_returns_false(): void
    {
        $result = $this->service->changeInterval('recurring@'.Uuid::uuid4()->toString(), 7200);

        $this->assertFalse($result);
    }

    public function test_change_interval_throws_exception_when_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interval seconds must be at least 1 second.');

        $this->service->changeInterval('recurring@'.Uuid::uuid4()->toString(), 0);
    }

    // ==================== TESTS: Statistics ====================

    public function test_get_stats(): void
    {
        $record = new SendNowRecord;
        $this->service->sendNow($this->user, $this->message, $record);

        $stats = $this->service->getStats($this->user);

        $this->assertInstanceOf(NotificationStatsVO::class, $stats);
        $this->assertEquals(4, $stats->total);
        $this->assertEquals(4, $stats->sent);
        $this->assertEquals(0, $stats->failed);
        $this->assertEquals(0, $stats->delivered);
        $this->assertEquals(0, $stats->pending);
        $this->assertEquals(100, $stats->success_rate);
    }

    public function test_get_stats_with_no_notifications(): void
    {
        $stats = $this->service->getStats($this->user);

        $this->assertInstanceOf(NotificationStatsVO::class, $stats);
        $this->assertEquals(0, $stats->total);
        $this->assertEquals(0, $stats->sent);
        $this->assertEquals(0, $stats->failed);
        $this->assertEquals(0, $stats->delivered);
        $this->assertEquals(0, $stats->pending);
        $this->assertEquals(0, $stats->success_rate);
    }

    public function test_get_session_stats(): void
    {
        $record = new SendNowRecord;
        $this->service->sendNow($this->user, $this->message, $record);

        $filter = NotificationFilterRecord::from([
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
        ]);

        $notifications = $this->repository->findBy(
            new FindByRecord(filters: $filter)
        );

        $sessionId = $notifications->first()->getSessionId();

        $stats = $this->service->getSessionStats($sessionId);

        $this->assertInstanceOf(SessionStatsRecord::class, $stats);
        $this->assertEquals($sessionId, $stats->session_id);
        $this->assertEquals(4, $stats->total);
        $this->assertEquals(4, $stats->sent);
        $this->assertEquals(0, $stats->failed);
        $this->assertEquals(0, $stats->pending);
    }

    public function test_get_session_stats_with_non_existing_session(): void
    {
        $nonExistingSessionId = (string) Uuid::uuid4();

        $stats = $this->service->getSessionStats($nonExistingSessionId);

        $this->assertInstanceOf(SessionStatsRecord::class, $stats);
        $this->assertEquals($nonExistingSessionId, $stats->session_id);
        $this->assertEquals(0, $stats->total);
        $this->assertEquals(0, $stats->sent);
        $this->assertEquals(0, $stats->failed);
        $this->assertEquals(0, $stats->pending);
    }

    // ==================== TESTS: Edge Cases ====================

    public function test_send_now_with_null_channels(): void
    {
        $record = new SendNowRecord;

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(4, $results);
    }

    public function test_send_now_with_multiple_same_channels(): void
    {
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendNowRecord(
            channels: $channels
        );

        $results = $this->service->sendNow($this->user, $this->message, $record);

        $this->assertCount(1, $results);
    }

    public function test_send_later_creates_unique_signature(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendLaterRecord(delay_seconds: 300);

        $alias1 = $this->service->sendLater($this->user, $this->message, $record);
        $alias2 = $this->service->sendLater($this->user, $this->message, $record);

        $this->assertNotEquals($alias1->getValue(), $alias2->getValue());
    }

    public function test_send_recurring_creates_unique_signature(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        $alias1 = $this->service->sendRecurring($this->user, $this->message, $record);
        $alias2 = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->assertNotEquals($alias1->getValue(), $alias2->getValue());
    }
}
