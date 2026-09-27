<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Tasks;

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Helpers\FcmPingPong;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Tasks\PingAndPruneFcmDeviceTask;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Directives\TasksProcessDirective;
use AndyDefer\Task\Enums\UniqueTaskStatus;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\Repositories\TaskExecutionDebugRepository;
use AndyDefer\Task\Repositories\UniqueTaskRepository;
use AndyDefer\Task\Services\UniqueTaskService;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

final class PingAndPruneFcmDeviceTaskTest extends TestCase
{
    use RefreshDatabase;

    private TestUser $user;

    private UniqueTaskServiceInterface $uniqueTaskService;

    private UniqueTaskRepository $uniqueTaskRepository;

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
        $this->uniqueTaskRepository = new UniqueTaskRepository(
            $debugRepository,
            $this->app->make(LoggerInterface::class),
        );

        $this->uniqueTaskService = new UniqueTaskService(
            repository: $this->uniqueTaskRepository,
            logger: $this->app->make(LoggerInterface::class),
            hydration: $this->app->make(HydrationService::class),
            app: $this->app,
        );

        $this->app->singleton(
            FcmPingPong::class,
            fn ($app) => new FcmPingPong(
                $app->make(NotificationServiceInterface::class),
            ),
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
        $this->directiveService->run('tasks:process');
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

    private function createConfig(): UniqueTaskConfigRecord
    {
        return new UniqueTaskConfigRecord(
            description: new DescriptionVO('Test ping and prune'),
            scheduled_at: new Iso8601DateTimeVO(now()->subHours(2)->toIso8601String()),
            max_attempts: new MaxFailedAttemptsVO(3),
            grace_period: new DurationVO(3600),
        );
    }

    public function test_register_and_execute_ping_success(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');
        $device = $this->createDevice($token);

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneFcmDeviceTask::class),
            StrictDataObject::from(['device_id' => (string) $device->id]),
            $this->createConfig(),
        );

        $this->processTasks();

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::COMPLETED, $taskModel->getStatus());

        $this->assertDatabaseHas('fcm_devices', ['id' => $device->id]);
    }

    public function test_task_deletes_device_when_invalid(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $device = $this->createDevice(str_repeat('a', 200));
        $deviceId = $device->id;

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneFcmDeviceTask::class),
            StrictDataObject::from(['device_id' => (string) $device->id]),
            $this->createConfig(),
        );

        $this->processTasks();

        $this->assertDatabaseMissing('fcm_devices', ['id' => $deviceId]);
    }

    public function test_task_keeps_device_when_unreachable(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        // Désactive Firebase pour provoquer une erreur "unreachable"
        $this->app['config']->set('notification.channels.firebase.enabled', false);

        $device = $this->createDevice(str_repeat('a', 200));
        $deviceId = $device->id;

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneFcmDeviceTask::class),
            StrictDataObject::from(['device_id' => (string) $device->id]),
            $this->createConfig(),
        );

        $this->processTasks();

        $this->assertDatabaseHas('fcm_devices', ['id' => $deviceId]);
    }

    public function test_task_fails_when_device_not_found(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneFcmDeviceTask::class),
            StrictDataObject::from(['device_id' => '01a0de28-605a-7208-8b40-11d0888080f6']),
            $this->createConfig(),
        );

        $this->processTasks();

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
    }

    public function test_task_fails_when_device_id_missing(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneFcmDeviceTask::class),
            StrictDataObject::from([]),
            $this->createConfig(),
        );

        $this->processTasks();

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
    }

    public function test_task_persists_last_seen_at_on_pong(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');
        $device = $this->createDevice($token);

        $before = $device->last_seen_at;

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneFcmDeviceTask::class),
            StrictDataObject::from(['device_id' => (string) $device->id]),
            $this->createConfig(),
        );

        $this->processTasks();

        $device->refresh();
        $this->assertTrue($device->last_seen_at->greaterThan($before));
    }
}
