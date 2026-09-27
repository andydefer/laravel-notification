<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Tasks;

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\Tasks\PruneFailedFcmNotificationsTask;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Directives\TasksProcessDirective;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class PruneFailedFcmNotificationsTaskTest extends TestCase
{
    use RefreshDatabase;

    private TestUser $user;

    private RecurringTaskServiceInterface $recurringTaskService;

    private RecurringTaskRepository $recurringTaskRepository;

    private DirectiveTestingService $directiveService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+33123456789',
        ]);

        $debugRepository = new TaskExecutionDebugRepository;
        $this->recurringTaskRepository = new RecurringTaskRepository(
            $debugRepository,
            $this->app->make(LoggerInterface::class),
        );

        $this->recurringTaskService = new RecurringTaskService(
            repository: $this->recurringTaskRepository,
            logger: $this->app->make(LoggerInterface::class),
            hydration: $this->app->make(HydrationService::class),
            app: $this->app,
        );

        $this->directiveService = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: [],
        );

        $this->directiveService
            ->getKernel()
            ->addDirective(TasksProcessDirective::class);
    }

    protected function tearDown(): void
    {
        $this->directiveService->destroy();
        $this->user->delete();
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function processTasks(): void
    {
        Carbon::setTestNow(Carbon::now()->addSeconds(20));
        $this->directiveService->run('tasks:process --verbose');
    }

    private function createDevice(string $token, string $deviceId = '550e8400-e29b-41d4-a716-446655440000'): FcmDevice
    {
        return FcmDevice::create([
            'device_id' => $deviceId,
            'token' => $token,
            'platform' => 'web',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => (string) $this->user->getKey(),
            'last_seen_at' => now()->subDay(),
        ]);
    }

    private function createFailedNotification(): Notification
    {
        return Notification::create([
            'id' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'channel' => FirebaseCloudMessagingChannel::class,
            'destination' => 'some-token',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'message' => 'Test failed notification',
            'status' => NotificationStatus::FAILED->value,
        ]);
    }

    private function createConfig(int $intervalSeconds = 1800): RecurringTaskConfigRecord
    {
        return RecurringTaskConfigRecord::from([
            'description' => new DescriptionVO('Test prune failed FCM'),
            'interval_seconds' => new CounterVO($intervalSeconds),
            'start_at' => new Iso8601DateTimeVO(now()->subHours(2)->toIso8601String()),
            'max_attempts' => new MaxFailedAttemptsVO(3),
        ]);
    }

    public function test_register_and_execute_task(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $this->createDevice('token-abc');
        $this->createFailedNotification();

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
            StrictDataObject::from(['enabled' => true]),
            $this->createConfig(),
        );

        $this->processTasks();

        $taskModel = $this->recurringTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(RecurringTaskStatus::PLAYING, $taskModel->getStatus());
        $this->assertNotNull($taskModel->getLastRunAt());
    }

    public function test_task_schedules_ping_and_prune_per_device(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $this->createDevice('token-abc', '550e8400-e29b-41d4-a716-446655440000');
        $this->createDevice('token-def', '550e8400-e29b-41d4-a716-446655440001');
        $this->createFailedNotification();

        $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
            StrictDataObject::from(['enabled' => true]),
            $this->createConfig(),
        );

        $this->processTasks();

        // ✅ 2 devices → 2 tâches PingAndPruneFcmDeviceTask enregistrées
        $this->assertDatabaseCount('unique_tasks', 2);
    }

    public function test_task_does_nothing_when_no_failed_notification(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $this->createDevice('token-abc');

        $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
            StrictDataObject::from(['enabled' => true]),
            $this->createConfig(),
        );

        $this->processTasks();

        // Aucune tâche unique créée
        $this->assertDatabaseCount('unique_tasks', 0);
    }

    public function test_task_ignores_notifications_from_other_channels(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $this->createDevice('token-abc');

        Notification::create([
            'id' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'channel' => MailChannel::class,
            'destination' => 'john@example.com',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'message' => 'Mail failed',
            'status' => NotificationStatus::FAILED->value,
        ]);

        $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
            StrictDataObject::from(['enabled' => true]),
            $this->createConfig(),
        );

        $this->processTasks();

        $this->assertDatabaseCount('unique_tasks', 0);
    }

    public function test_task_ignores_notifications_with_sent_status(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $this->createDevice('token-abc');

        Notification::create([
            'id' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'channel' => FirebaseCloudMessagingChannel::class,
            'destination' => 'some-token',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => $this->user->getKey(),
            'message' => 'Success',
            'status' => NotificationStatus::SENT->value,
        ]);

        $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
            StrictDataObject::from(['enabled' => true]),
            $this->createConfig(),
        );

        $this->processTasks();

        $this->assertDatabaseCount('unique_tasks', 0);
    }

    public function test_task_skips_notifications_without_notifiable(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $this->createDevice('token-abc');

        Notification::create([
            'id' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'channel' => FirebaseCloudMessagingChannel::class,
            'destination' => 'some-token',
            'notifiable_type' => TestUser::class,
            'notifiable_id' => 99999,
            'message' => 'Orphan notification',
            'status' => NotificationStatus::FAILED->value,
        ]);

        $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
            StrictDataObject::from(['enabled' => true]),
            $this->createConfig(),
        );

        $this->processTasks();

        $this->assertDatabaseCount('unique_tasks', 0);
    }

    public function test_task_processes_multiple_failed_notifications_without_duplicates(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $this->createDevice('token-abc', '550e8400-e29b-41d4-a716-446655440000');

        $this->createFailedNotification();
        $this->createFailedNotification();
        $this->createFailedNotification();

        $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
            StrictDataObject::from(['enabled' => true]),
            $this->createConfig(),
        );

        $this->processTasks();

        // ✅ 1 device, 3 notifications → 3 tâches uniques (pas de déduplication par device)
        $this->assertDatabaseCount('unique_tasks', 3);
    }
}
