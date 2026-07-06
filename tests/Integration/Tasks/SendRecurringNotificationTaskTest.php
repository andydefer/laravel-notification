<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Tasks;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Enums\RecurringTaskStatus;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\Repositories\RecurringTaskRepository;
use AndyDefer\Task\Repositories\TaskExecutionDebugRepository;
use AndyDefer\Task\Services\RecurringTaskService;
use AndyDefer\Task\ValueObjects\CounterVO;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

final class SendRecurringNotificationTaskTest extends TestCase
{
    use RefreshDatabase;

    private TestUser $user;

    private RecurringTaskServiceInterface $recurringTaskService;

    private RecurringTaskRepository $recurringTaskRepository;

    private NotificationServiceInterface $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->notificationService = $this->app->make(NotificationServiceInterface::class);

        $debugRepository = new TaskExecutionDebugRepository;
        $this->recurringTaskRepository = new RecurringTaskRepository(
            $debugRepository,
            $this->app->make(LoggerInterface::class)
        );

        $this->recurringTaskService = new RecurringTaskService(
            repository: $this->recurringTaskRepository,
            logger: $this->app->make(LoggerInterface::class),
            hydration: $this->app->make(HydrationService::class),
            app: $this->app,
        );
    }

    protected function tearDown(): void
    {
        $this->user->delete();
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function createPayload(array $data): StrictDataObject
    {
        return StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'body' => $data['body'] ?? 'Test message',
            'subject' => $data['subject'] ?? 'Test Subject',
            'type' => $data['type'] ?? 'test',
            'data' => $data['extra_data'] ?? [],
            'channels' => $data['channels'] ?? null,
            'limit_per_channel' => $data['limit_per_channel'] ?? null,
        ]);
    }

    private function createConfig(int $intervalSeconds, Iso8601DateTimeVO $startAt, ?Iso8601DateTimeVO $endAt = null, int $maxAttempts = 3): RecurringTaskConfigRecord
    {
        return new RecurringTaskConfigRecord(
            description: new DescriptionVO('Test recurring notification'),
            interval_seconds: new CounterVO($intervalSeconds),
            start_at: $startAt,
            end_at: $endAt,
            max_attempts: new MaxFailedAttemptsVO($maxAttempts),
        );
    }

    public function test_register_and_execute_task_when_playing(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Rappel quotidien',
            'subject' => 'Rappel de traitement',
            'type' => 'daily_reminder',
            'extra_data' => ['medication' => 'Paracétamol'],
            'channels' => [MailChannel::class],
        ]);

        $config = $this->createConfig(
            86400,
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::PLAYING, $taskModel->getStatus());

        $result = $this->recurringTaskService->run($alias);
        $this->assertTrue($result->success);

        $updatedTask = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertEquals(RecurringTaskStatus::PLAYING, $updatedTask->getStatus());
        $this->assertNotNull($updatedTask->getLastRunAt());

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
        ]);
    }

    public function test_task_not_executed_when_waiting(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Test future recurring',
            'subject' => 'Future Subject',
            'type' => 'future',
            'channels' => [MailChannel::class],
        ]);

        $config = $this->createConfig(
            3600,
            new Iso8601DateTimeVO($frozenNow->copy()->addHours(2)->toIso8601String())
        );

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::WAITING, $taskModel->getStatus());

        $result = $this->recurringTaskService->run($alias);
        $this->assertFalse($result->success);

        $updatedTask = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertEquals(RecurringTaskStatus::WAITING, $updatedTask->getStatus());
        $this->assertNull($updatedTask->getLastRunAt());
    }

    public function test_task_not_executed_when_finished(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Test ended recurring',
            'subject' => 'Ended Subject',
            'type' => 'ended',
            'channels' => [MailChannel::class],
        ]);

        $config = $this->createConfig(
            3600,
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(3)->toIso8601String()),
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(1)->toIso8601String())
        );

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::FINISHED, $taskModel->getStatus());

        $result = $this->recurringTaskService->run($alias);
        $this->assertFalse($result->success);
    }

    public function test_task_not_executed_when_paused(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Test paused recurring',
            'subject' => 'Paused Subject',
            'type' => 'paused',
            'channels' => [MailChannel::class],
        ]);

        $config = $this->createConfig(
            3600,
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );

        $this->recurringTaskService->pause($alias);

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::PAUSED, $taskModel->getStatus());

        $result = $this->recurringTaskService->run($alias);
        $this->assertFalse($result->success);
    }

    public function test_task_fails_when_notifiable_not_found(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => 99999,
            'body' => 'Test message',
            'subject' => 'Test Subject',
            'type' => 'test',
            'data' => [],
            'channels' => [MailChannel::class],
            'limit_per_channel' => null,
        ]);

        $config = $this->createConfig(
            3600,
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::PLAYING, $taskModel->getStatus());

        $result = $this->recurringTaskService->run($alias);
        $this->assertFalse($result->success);

        $updatedTask = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($updatedTask->getLastRunAt());
    }

    public function test_register_and_execute_with_channels(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Message avec canal mail',
            'subject' => 'Test Canal',
            'type' => 'channel_test',
            'channels' => [MailChannel::class],
        ]);

        $config = $this->createConfig(
            3600,
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::PLAYING, $taskModel->getStatus());

        $result = $this->recurringTaskService->run($alias);
        $this->assertTrue($result->success);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
        ]);
    }

    public function test_register_and_execute_with_channel_collection(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $channels = FqcnChannelCollection::from([MailChannel::class, SmsChannel::class]);

        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'body' => 'Message avec canaux',
            'subject' => 'Test Canaux',
            'type' => 'channel_collection',
            'data' => [],
            'channels' => $channels,
            'limit_per_channel' => null,
        ]);

        $config = $this->createConfig(
            3600,
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::PLAYING, $taskModel->getStatus());

        $result = $this->recurringTaskService->run($alias);
        $this->assertTrue($result->success);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
        ]);
    }

    public function test_task_fails_when_class_not_found(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = StrictDataObject::from([
            'notifiable_type' => 'NonExistentClass',
            'notifiable_id' => 1,
            'body' => 'Test',
            'subject' => 'Test Subject',
            'type' => 'test',
            'data' => [],
            'channels' => [MailChannel::class],
            'limit_per_channel' => null,
        ]);

        $config = $this->createConfig(
            3600,
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::PLAYING, $taskModel->getStatus());

        $result = $this->recurringTaskService->run($alias);
        $this->assertFalse($result->success);
    }

    public function test_task_validates_payload_before_execution(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        // Payload sans body (devrait échouer la validation)
        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'subject' => 'Test Subject',
            'type' => 'test',
            'data' => [],
            'channels' => [MailChannel::class],
            'limit_per_channel' => null,
        ]);

        $config = $this->createConfig(
            3600,
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->recurringTaskService->run($alias);
        $this->assertFalse($result->success);
    }
}
