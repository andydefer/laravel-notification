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
        // Arrange : Create a send now record
        $record = new SendNowRecord;

        // Act : Send the notification
        $results = $this->service->sendNow($this->user, $this->message, $record);

        // Assert : Verify all channels were used
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
        // Arrange : Create a send now record with only Mail channel
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendNowRecord(
            channels: $channels
        );

        // Act : Send the notification
        $results = $this->service->sendNow($this->user, $this->message, $record);

        // Assert : Verify only Mail channel was used
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
        // Arrange : Create a send now record with limit
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendNowRecord(
            channels: $channels,
            limit_per_channel: 1
        );

        // Act : Send the notification
        $results = $this->service->sendNow($this->user, $this->message, $record);

        // Assert : Verify limit was applied
        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(1, $results);
    }

    public function test_send_now_with_empty_channels(): void
    {
        // Arrange : Create a send now record with empty channels
        $channels = new FqcnChannelCollection;

        $record = new SendNowRecord(
            channels: $channels
        );

        // Act : Send the notification
        $results = $this->service->sendNow($this->user, $this->message, $record);

        // Assert : Verify all channels were used (fallback)
        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(4, $results);
    }

    public function test_send_now_throws_exception_when_no_channels_available(): void
    {
        // Arrange : Create a user with no channels
        $user = TestEmptyChannel::create(['name' => 'No Channels']);

        $record = new SendNowRecord;

        // Expect : Exception should be thrown
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No available channels for notifiable');

        // Act : Attempt to send the notification
        $this->service->sendNow($user, $this->message, $record);
    }

    // ==================== TESTS: sendLater ====================

    public function test_send_later_schedules_task(): void
    {
        // Arrange : Freeze time and create a send later record
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendLaterRecord(
            delay_seconds: 300
        );

        // Act : Schedule the notification
        $alias = $this->service->sendLater($this->user, $this->message, $record);

        // Assert : Verify the task was created
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(SendDelayedNotificationTask::class, $task->getFqcn());
    }

    public function test_send_later_with_channels(): void
    {
        // Arrange : Freeze time and create a send later record with channels
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendLaterRecord(
            delay_seconds: 300,
            channels: $channels
        );

        // Act : Schedule the notification
        $alias = $this->service->sendLater($this->user, $this->message, $record);

        // Assert : Verify the task has the channel in payload
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();
        $this->assertContains(MailChannel::class, $payload->get('channels'));
    }

    public function test_send_later_with_limit_per_channel(): void
    {
        // Arrange : Freeze time and create a send later record with limit
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendLaterRecord(
            delay_seconds: 300,
            limit_per_channel: 2
        );

        // Act : Schedule the notification
        $alias = $this->service->sendLater($this->user, $this->message, $record);

        // Assert : Verify the task has the limit in payload
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();
        $this->assertEquals(2, $payload->get('limit_per_channel'));
    }

    public function test_send_later_throws_exception_when_delay_zero(): void
    {
        // Arrange : Create a send later record with zero delay
        $record = new SendLaterRecord(
            delay_seconds: 0
        );

        // Expect : Exception should be thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Delay seconds must be greater than 0.');

        // Act : Attempt to schedule the notification
        $this->service->sendLater($this->user, $this->message, $record);
    }

    public function test_send_later_throws_exception_when_delay_negative(): void
    {
        // Arrange : Create a send later record with negative delay
        $record = new SendLaterRecord(
            delay_seconds: -5
        );

        // Expect : Exception should be thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Delay seconds must be greater than 0.');

        // Act : Attempt to schedule the notification
        $this->service->sendLater($this->user, $this->message, $record);
    }

    // ==================== TESTS: sendAt ====================

    public function test_send_at_schedules_task_at_specific_time(): void
    {
        // Arrange : Freeze time and create a send at record
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $scheduledAt = new NotificationDateTimeVO($frozenNow->copy()->addHours(2)->toIso8601String());

        $record = new SendAtRecord(
            scheduled_at: $scheduledAt
        );

        // Act : Schedule the notification
        $alias = $this->service->sendAt($this->user, $this->message, $record);

        // Assert : Verify the task was created with correct time
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $this->assertEquals(
            $frozenNow->copy()->addHours(2)->toIso8601String(),
            $task->getScheduledAt()->getValue()
        );
    }

    public function test_send_at_with_channels(): void
    {
        // Arrange : Freeze time and create a send at record with channels
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $scheduledAt = new NotificationDateTimeVO($frozenNow->copy()->addHours(2)->toIso8601String());

        $record = new SendAtRecord(
            scheduled_at: $scheduledAt,
            channels: $channels
        );

        // Act : Schedule the notification
        $alias = $this->service->sendAt($this->user, $this->message, $record);

        // Assert : Verify the task has the channel in payload
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);

        $payload = $task->getPayload();
        $this->assertContains(MailChannel::class, $payload->get('channels'));
    }

    public function test_send_at_throws_exception_when_date_in_past(): void
    {
        // Arrange : Freeze time and create a send at record with past date
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $scheduledAt = new NotificationDateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String());

        $record = new SendAtRecord(
            scheduled_at: $scheduledAt
        );

        // Expect : Exception should be thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scheduled date must be in the future.');

        // Act : Attempt to schedule the notification
        $this->service->sendAt($this->user, $this->message, $record);
    }

    public function test_send_at_throws_exception_when_date_now(): void
    {
        // Arrange : Freeze time and create a send at record with current date
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $scheduledAt = new NotificationDateTimeVO($frozenNow->toIso8601String());

        $record = new SendAtRecord(
            scheduled_at: $scheduledAt
        );

        // Expect : Exception should be thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scheduled date must be in the future.');

        // Act : Attempt to schedule the notification
        $this->service->sendAt($this->user, $this->message, $record);
    }

    // ==================== TESTS: sendRecurring ====================

    public function test_send_recurring_schedules_recurring_task(): void
    {
        // Arrange : Freeze time and create a send recurring record
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        // Act : Schedule the recurring notification
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        // Assert : Verify the recurring task was created
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(SendRecurringNotificationTask::class, $task->getFqcn());
        $this->assertEquals(3600, $task->getIntervalSeconds()->getValue());
    }

    public function test_send_recurring_with_channels(): void
    {
        // Arrange : Freeze time and create a send recurring record with channels
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String()),
            channels: $channels
        );

        // Act : Schedule the recurring notification
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        // Assert : Verify the task has the channel in payload
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $payload = $task->getPayload();
        $this->assertContains(MailChannel::class, $payload->get('channels'));
    }

    public function test_send_recurring_with_end_at(): void
    {
        // Arrange : Freeze time and create a send recurring record with end date
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $endAt = new NotificationDateTimeVO($frozenNow->copy()->addDays(7)->toIso8601String());

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String()),
            end_at: $endAt
        );

        // Act : Schedule the recurring notification
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        // Assert : Verify the end date was set
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals($endAt->getValue(), $task->getEndAt()?->getValue());
    }

    public function test_send_recurring_with_limit_per_channel(): void
    {
        // Arrange : Freeze time and create a send recurring record with limit
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String()),
            limit_per_channel: 2
        );

        // Act : Schedule the recurring notification
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        // Assert : Verify the limit was set
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $payload = $task->getPayload();
        $this->assertEquals(2, $payload->get('limit_per_channel'));
    }

    public function test_send_recurring_with_max_attempts(): void
    {
        // Arrange : Freeze time and create a send recurring record with max attempts
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String()),
            max_attempts: new MaxFailedAttemptsVO(5)
        );

        // Act : Schedule the recurring notification
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        // Assert : Verify the max attempts was set
        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(5, $task->getMaxFailedAttempts()->getValue());
    }

    public function test_send_recurring_throws_exception_when_interval_zero(): void
    {
        // Arrange : Freeze time and create a send recurring record with zero interval
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 0,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        // Expect : Exception should be thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interval seconds must be at least 1 second.');

        // Act : Attempt to schedule the recurring notification
        $this->service->sendRecurring($this->user, $this->message, $record);
    }

    public function test_send_recurring_throws_exception_when_interval_negative(): void
    {
        // Arrange : Freeze time and create a send recurring record with negative interval
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: -1,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        // Expect : Exception should be thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interval seconds must be at least 1 second.');

        // Act : Attempt to schedule the recurring notification
        $this->service->sendRecurring($this->user, $this->message, $record);
    }

    // ==================== TESTS: Task Management ====================

    public function test_cancel_unique_task(): void
    {
        // Arrange : Schedule a unique task
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendLaterRecord(delay_seconds: 300);
        $alias = $this->service->sendLater($this->user, $this->message, $record);

        // Act : Cancel the task
        $result = $this->service->cancel($alias->getValue());

        // Assert : Verify the task was cancelled
        $this->assertTrue($result);
    }

    public function test_cancel_recurring_task(): void
    {
        // Arrange : Schedule a recurring task
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        // Act : Cancel the task
        $result = $this->service->cancel($alias->getValue());

        // Assert : Verify the task was cancelled
        $this->assertTrue($result);
    }

    public function test_cancel_non_existing_task_returns_false(): void
    {
        // Act : Attempt to cancel a non-existing task
        $result = $this->service->cancel('unique@'.Uuid::uuid4()->toString());

        // Assert : Verify false is returned
        $this->assertFalse($result);
    }

    public function test_pause_recurring_task(): void
    {
        // Arrange : Schedule a recurring task
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        // Act : Pause the task
        $result = $this->service->pause($alias->getValue());

        // Assert : Verify the task was paused
        $this->assertTrue($result);
    }

    public function test_pause_non_existing_task_returns_false(): void
    {
        // Act : Attempt to pause a non-existing task
        $result = $this->service->pause('recurring@'.Uuid::uuid4()->toString());

        // Assert : Verify false is returned
        $this->assertFalse($result);
    }

    public function test_resume_recurring_task(): void
    {
        // Arrange : Schedule and pause a recurring task
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        $this->service->pause($alias->getValue());

        // Act : Resume the task
        $result = $this->service->resume($alias->getValue());

        // Assert : Verify the task was resumed
        $this->assertTrue($result);
    }

    public function test_resume_non_existing_task_returns_false(): void
    {
        // Act : Attempt to resume a non-existing task
        $result = $this->service->resume('recurring@'.Uuid::uuid4()->toString());

        // Assert : Verify false is returned
        $this->assertFalse($result);
    }

    public function test_change_interval_recurring_task(): void
    {
        // Arrange : Schedule a recurring task
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );
        $alias = $this->service->sendRecurring($this->user, $this->message, $record);

        // Act : Change the interval
        $result = $this->service->changeInterval($alias->getValue(), 7200);

        // Assert : Verify the interval was changed
        $this->assertTrue($result);

        $task = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($task);
        $this->assertEquals(7200, $task->getIntervalSeconds()->getValue());
    }

    public function test_change_interval_non_existing_task_returns_false(): void
    {
        // Act : Attempt to change interval of a non-existing task
        $result = $this->service->changeInterval('recurring@'.Uuid::uuid4()->toString(), 7200);

        // Assert : Verify false is returned
        $this->assertFalse($result);
    }

    public function test_change_interval_throws_exception_when_zero(): void
    {
        // Expect : Exception should be thrown for zero interval
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interval seconds must be at least 1 second.');

        // Act : Attempt to change interval to zero
        $this->service->changeInterval('recurring@'.Uuid::uuid4()->toString(), 0);
    }

    // ==================== TESTS: Statistics ====================

    public function test_get_stats(): void
    {
        // Arrange : Send a notification
        $record = new SendNowRecord;
        $this->service->sendNow($this->user, $this->message, $record);

        // Act : Get stats
        $stats = $this->service->getStats($this->user);

        // Assert : Verify stats are correct
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
        // Act : Get stats without any notifications
        $stats = $this->service->getStats($this->user);

        // Assert : Verify stats are zero
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
        // Arrange : Send a notification
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

        // Act : Get session stats
        $stats = $this->service->getSessionStats($sessionId);

        // Assert : Verify session stats are correct
        $this->assertInstanceOf(SessionStatsRecord::class, $stats);
        $this->assertEquals($sessionId, $stats->session_id);
        $this->assertEquals(4, $stats->total);
        $this->assertEquals(4, $stats->sent);
        $this->assertEquals(0, $stats->failed);
        $this->assertEquals(0, $stats->pending);
    }

    public function test_get_session_stats_with_non_existing_session(): void
    {
        // Arrange : Create a non-existing session ID
        $nonExistingSessionId = (string) Uuid::uuid4();

        // Act : Get session stats for non-existing session
        $stats = $this->service->getSessionStats($nonExistingSessionId);

        // Assert : Verify stats are zero
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
        // Arrange : Create a send now record without channels
        $record = new SendNowRecord;

        // Act : Send the notification
        $results = $this->service->sendNow($this->user, $this->message, $record);

        // Assert : Verify all channels were used
        $this->assertInstanceOf(SendResultCollection::class, $results);
        $this->assertCount(4, $results);
    }

    public function test_send_now_with_multiple_same_channels(): void
    {
        // Arrange : Create a send now record with duplicate channels
        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(MailChannel::class));

        $record = new SendNowRecord(
            channels: $channels
        );

        // Act : Send the notification
        $results = $this->service->sendNow($this->user, $this->message, $record);

        // Assert : Verify duplicate channels are deduplicated
        $this->assertCount(1, $results);
    }

    public function test_send_later_creates_unique_signature(): void
    {
        // Arrange : Freeze time
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendLaterRecord(delay_seconds: 300);

        // Act : Schedule two notifications
        $alias1 = $this->service->sendLater($this->user, $this->message, $record);
        $alias2 = $this->service->sendLater($this->user, $this->message, $record);

        // Assert : Verify aliases are unique
        $this->assertNotEquals($alias1->getValue(), $alias2->getValue());
    }

    public function test_send_recurring_creates_unique_signature(): void
    {
        // Arrange : Freeze time
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $record = new SendRecurringRecord(
            interval_seconds: 3600,
            start_at: new NotificationDateTimeVO($frozenNow->toIso8601String())
        );

        // Act : Schedule two recurring notifications
        $alias1 = $this->service->sendRecurring($this->user, $this->message, $record);
        $alias2 = $this->service->sendRecurring($this->user, $this->message, $record);

        // Assert : Verify aliases are unique
        $this->assertNotEquals($alias1->getValue(), $alias2->getValue());
    }
}
