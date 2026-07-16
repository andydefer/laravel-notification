<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Tasks;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\LaravelNotification\Tests\Fixtures\Channels\TestChannel;
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
use Illuminate\Support\Facades\DB;

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

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_secondary' => 'admin@example.com', // ✅ Ajouté
            'phone' => '+33123456789',
        ]);

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
            'destination_filter' => $data['destination_filter'] ?? null,
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

    // ==================== TESTS: Basic Execution ====================

    public function test_register_and_execute_task(): void
    {
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

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

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

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::PENDING, $taskModel->getStatus());

        $result = $this->uniqueTaskService->run($alias);

        // ✅ La tâche est "skippée" car elle est dans le futur, donc success = true
        $this->assertTrue($result->success);
        $this->assertTrue($result->skipped);
        $this->assertSame('Task is scheduled in the future - skipped', $result->message);

        $updatedTask = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertEquals(UniqueTaskStatus::PENDING, $updatedTask->getStatus());
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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String()),
            1
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        $this->assertFalse($result->success);

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String()),
            1
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        $this->assertFalse($result->success);

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String()),
            1
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        $this->assertFalse($result->success);

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

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
            'channels' => [MailChannel::class, TestChannel::class], // ✅ TestChannel au lieu de SmsChannel
            'destination_filter' => [
                MailChannel::class => ['john@example.com', 'admin@example.com'], // ✅ Ajout admin
                TestChannel::class => ['+33123456789'],
            ],
        ]);

        $config = $this->createConfig(
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

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
            'email_secondary' => 'secondary@example.com', // ✅ Ajouté
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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        // ✅ La tâche échoue car aucune destination ne correspond
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
            'email_secondary' => 'limit2@example.com', // ✅ Ajouté
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
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        $this->assertTrue($result->success);

        $notifications = DB::table('notifications')
            ->where('notifiable_type', TestUser::class)
            ->where('notifiable_id', $user->getKey())
            ->where('channel', MailChannel::class)
            ->get();

        $this->assertCount(1, $notifications);
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
            'channels' => [], // ✅ Vide - devrait échouer
            'limit_per_channel' => null,
        ]);

        $config = $this->createConfig(
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String()),
            1
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        // ✅ La tâche échoue car channels est vide
        $this->assertFalse($result->success);

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
    }

    public function test_task_validates_empty_body(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'body' => '', // ✅ Vide - devrait échouer
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

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        $this->assertFalse($result->success);

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
    }

    public function test_task_validates_empty_subject(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $payload = StrictDataObject::from([
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
            'body' => 'Test message',
            'subject' => '', // ✅ Vide - devrait échouer
            'type' => 'test',
            'data' => [],
            'channels' => [MailChannel::class],
            'limit_per_channel' => null,
        ]);

        $config = $this->createConfig(
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String()),
            1
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        $this->assertFalse($result->success);

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
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
            'destination_filter' => [], // ✅ Filtre vide
        ]);

        $config = $this->createConfig(
            new Iso8601DateTimeVO($frozenNow->copy()->subHours(2)->toIso8601String())
        );

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        $result = $this->uniqueTaskService->run($alias);

        // ✅ Filtre vide = pas de filtre, la notification est envoyée
        $this->assertTrue($result->success);

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => TestUser::class,
            'notifiable_id' => $this->user->getKey(),
        ]);
    }
}
