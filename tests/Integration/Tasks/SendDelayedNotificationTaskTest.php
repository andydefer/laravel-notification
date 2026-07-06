<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Tasks;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Enums\UniqueTaskStatus;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\Repositories\TaskExecutionDebugRepository;
use AndyDefer\Task\Repositories\UniqueTaskRepository;
use AndyDefer\Task\Services\UniqueTaskService;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

final class SendDelayedNotificationTaskTest extends TestCase
{
    use RefreshDatabase;

    private TestUser $user;

    private UniqueTaskServiceInterface $uniqueTaskService;

    private UniqueTaskRepository $uniqueTaskRepository;

    private NotificationServiceInterface $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange : Create a test user
        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Arrange : Get services from container
        $this->notificationService = $this->app->make(NotificationServiceInterface::class);

        $debugRepository = new TaskExecutionDebugRepository;
        $this->uniqueTaskRepository = new UniqueTaskRepository(
            $debugRepository,
            $this->app->make(LoggerInterface::class)
        );

        $this->uniqueTaskService = new UniqueTaskService(
            repository: $this->uniqueTaskRepository,
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

    private function createConfig(Iso8601DateTimeVO $scheduledAt, int $maxAttempts = 3): UniqueTaskConfigRecord
    {
        return new UniqueTaskConfigRecord(
            description: new DescriptionVO('Test delayed notification'),
            scheduled_at: $scheduledAt,
            max_attempts: new MaxFailedAttemptsVO($maxAttempts),
            grace_period: new DurationVO(86400),
        );
    }

    public function test_register_and_execute_task(): void
    {
        // Arrange : Freeze time and create task data
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Bienvenue sur notre plateforme',
            'subject' => 'Bienvenue',
            'type' => 'welcome',
            'extra_data' => ['user_id' => 1],
            'channels' => [MailChannel::class],
        ]);

        $config = $this->createConfig(
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        // Act : Register and run the task
        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        // Assert : Verify task was executed successfully
        $this->assertInstanceOf(TaskAliasVO::class, $alias);
        $this->assertTrue($result->success);

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::COMPLETED, $taskModel->getStatus());

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
        ]);
    }

    public function test_task_not_executed_when_scheduled_in_future(): void
    {
        // Arrange : Freeze time and create task scheduled in future
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Test future',
            'subject' => 'Future Subject',
            'type' => 'future',
            'channels' => [MailChannel::class],
        ]);

        $config = $this->createConfig(
            new Iso8601DateTimeVO($frozenNow->copy()->addHours(2)->toIso8601String())
        );

        // Act : Register the task
        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        // Assert : Task should be PENDING
        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::PENDING, $taskModel->getStatus());

        // Act : Try to run the task
        $result = $this->uniqueTaskService->run($alias);

        // Assert : Task should not execute
        $this->assertFalse($result->success);

        $updatedTask = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertEquals(UniqueTaskStatus::PENDING, $updatedTask->getStatus());
    }

    public function test_task_fails_when_notifiable_not_found(): void
    {
        // Arrange : Freeze time and create task with non-existent notifiable
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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String()),
            1
        );

        // Act : Register and run the task
        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        // Assert : Task should fail
        $this->assertFalse($result->success);

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
    }

    public function test_register_and_execute_with_channels(): void
    {
        // Arrange : Freeze time and create task with channels
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Message avec canal mail',
            'subject' => 'Test Canal',
            'type' => 'channel_test',
            'channels' => [MailChannel::class],
        ]);

        $config = $this->createConfig(
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        // Act : Register and run the task
        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        // Assert : Task should succeed and create notification
        $this->assertTrue($result->success);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
        ]);
    }

    public function test_register_and_execute_with_channel_collection(): void
    {
        // Arrange : Freeze time and create task with channel collection
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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        // Act : Register and run the task
        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        // Assert : Task should succeed and create notification
        $this->assertTrue($result->success);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
        ]);
    }

    public function test_task_fails_when_class_not_found(): void
    {
        // Arrange : Freeze time and create task with non-existent class
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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String()),
            1
        );

        // Act : Register and run the task
        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        // Assert : Task should fail
        $this->assertFalse($result->success);

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
    }

    public function test_task_validates_payload_before_execution(): void
    {
        // Arrange : Freeze time and create task without body (invalid)
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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String()),
            1
        );

        // Act : Register and run the task
        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        // Assert : Task should fail validation
        $this->assertFalse($result->success);

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
    }
}
