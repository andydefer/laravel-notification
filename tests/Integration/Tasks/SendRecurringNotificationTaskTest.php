<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Tasks;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\LaravelNotification\Tests\Fixtures\Channels\TestChannel;
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
use Illuminate\Support\Facades\DB;

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
            'email_secondary' => 'admin@example.com',
            'phone' => '+33123456789',
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
            'destination_filter' => $data['destination_filter'] ?? null,
        ]);
    }

    private function createConfig(
        int $intervalSeconds,
        Iso8601DateTimeVO $startAt,
        ?Iso8601DateTimeVO $endAt = null,
        int $maxAttempts = 3
    ): RecurringTaskConfigRecord {
        return new RecurringTaskConfigRecord(
            description: new DescriptionVO('Test recurring notification'),
            interval_seconds: new CounterVO($intervalSeconds),
            start_at: $startAt,
            end_at: $endAt,
            max_attempts: new MaxFailedAttemptsVO($maxAttempts),
        );
    }

    // ==================== TESTS: Basic Execution ====================

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

        $result = $this->recurringTaskService->run($alias);

        $this->assertInstanceOf(TaskAliasVO::class, $alias);

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::PLAYING, $taskModel->getStatus());

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

    // ==================== TESTS: Channels ====================

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

        $channels = FqcnChannelCollection::from([MailChannel::class, TestChannel::class]);

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

    // ==================== TESTS: Destination Filters ====================

    public function test_register_and_execute_with_destination_filter_single(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Message avec filtre mail',
            'subject' => 'Test Filtre',
            'type' => 'filter_test',
            'channels' => [MailChannel::class],
            'destination_filter' => [
                MailChannel::class => ['john@example.com'],
            ],
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
            'destination' => 'john@example.com',
        ]);
    }

    public function test_register_and_execute_with_destination_filter_multiple(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Message avec filtres multiples',
            'subject' => 'Test Filtres Multiples',
            'type' => 'filter_multiple',
            'channels' => [MailChannel::class, TestChannel::class],
            'destination_filter' => [
                MailChannel::class => ['john@example.com', 'admin@example.com'],
                TestChannel::class => ['+33123456789'],
            ],
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

        $this->assertTrue($result->success);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'destination' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'destination' => 'admin@example.com',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'destination' => '+33123456789',
        ]);
    }

    public function test_register_and_execute_with_destination_filter_multiple_destinations_same_channel(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $user = TestUser::create([
            'name' => 'Multi Email User',
            'email' => 'primary@example.com',
            'email_secondary' => 'secondary@example.com',
        ]);

        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $user->getKey(),
            'body' => 'Message avec emails multiples',
            'subject' => 'Test Emails Multiples',
            'type' => 'filter_multiple_emails',
            'data' => [],
            'channels' => [MailChannel::class],
            'limit_per_channel' => null,
            'destination_filter' => [
                MailChannel::class => ['primary@example.com', 'secondary@example.com'],
            ],
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

        $this->assertTrue($result->success);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $user->getKey(),
            'destination' => 'primary@example.com',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $user->getKey(),
            'destination' => 'secondary@example.com',
        ]);
    }

    public function test_register_and_execute_with_destination_filter_non_matching_skips_destination(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $user = TestUser::create([
            'name' => 'Different Email User',
            'email' => 'different@example.com',
            'phone' => '+33123456789',
        ]);

        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $user->getKey(),
            'body' => 'Message avec filtre non correspondant',
            'subject' => 'Test Filtre Non Correspondant',
            'type' => 'filter_non_matching',
            'data' => [],
            'channels' => [MailChannel::class],
            'limit_per_channel' => null,
            'destination_filter' => [
                MailChannel::class => ['non-matching@example.com'],
            ],
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

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $user->getKey(),
        ]);
    }

    public function test_register_and_execute_with_destination_filter_and_limit_per_channel(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $user = TestUser::create([
            'name' => 'Limit User',
            'email' => 'limit1@example.com',
            'email_secondary' => 'limit2@example.com',
        ]);

        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $user->getKey(),
            'body' => 'Message avec filtre et limite',
            'subject' => 'Test Filtre + Limite',
            'type' => 'filter_limit',
            'data' => [],
            'channels' => [MailChannel::class],
            'limit_per_channel' => 1,
            'destination_filter' => [
                MailChannel::class => ['limit1@example.com', 'limit2@example.com'],
            ],
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

        $this->assertTrue($result->success);

        $notifications = DB::table('notifications')
            ->where('notifiable_type', TestUser::class)
            ->where('notifiable_id', $user->getKey())
            ->where('channel', MailChannel::class)
            ->get();

        $this->assertCount(1, $notifications);
    }

    public function test_register_and_execute_with_destination_filter_and_recurring_task_persists(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Message récurrent avec filtre',
            'subject' => 'Test Récurrent Filtre',
            'type' => 'recurring_filter',
            'channels' => [MailChannel::class],
            'destination_filter' => [
                MailChannel::class => ['john@example.com'],
            ],
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

        $this->assertTrue($result->success);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'destination' => 'john@example.com',
        ]);

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::PLAYING, $taskModel->getStatus());
        $this->assertNotNull($taskModel->getLastRunAt());

        $payload = $taskModel->getPayload();
        $this->assertEquals('john@example.com', $payload->get('destination_filter')[MailChannel::class][0]);
    }

    // ==================== TESTS: Error Cases ====================

    public function test_task_validates_missing_channels(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'body' => 'Test',
            'subject' => 'Test Subject',
            'type' => 'test',
            'data' => [],
            'channels' => [],
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

    public function test_task_validates_empty_body(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'body' => '',
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

    public function test_task_validates_empty_subject(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'body' => 'Test message',
            'subject' => '',
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

    public function test_task_handles_empty_destination_filter_gracefully(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Test avec filtre vide',
            'subject' => 'Test Filtre Vide',
            'type' => 'filter_empty',
            'channels' => [MailChannel::class],
            'destination_filter' => [],
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

        $this->assertTrue($result->success);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
        ]);
    }

    public function test_task_validates_interval_minimum(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = $this->createPayload([
            'body' => 'Test interval trop court',
            'subject' => 'Test Interval',
            'type' => 'interval_test',
            'channels' => [MailChannel::class],
        ]);

        $config = $this->createConfig(
            30,
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
