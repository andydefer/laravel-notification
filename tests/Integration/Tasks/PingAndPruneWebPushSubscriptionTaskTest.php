<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Tasks;

use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Helpers\WebPushPingPong;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\LaravelNotification\Tasks\PingAndPruneWebPushSubscriptionTask;
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

final class PingAndPruneWebPushSubscriptionTaskTest extends TestCase
{
    use RefreshDatabase;

    private TestUser $user;

    private UniqueTaskServiceInterface $uniqueTaskService;

    private UniqueTaskRepository $uniqueTaskRepository;

    private DirectiveTestingService $directiveService;

    private string $endpoint;

    private string $p256dh;

    private string $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = (string) $this->getEnv('WEBPUSH_ENDPOINT');
        $this->p256dh = (string) $this->getEnv('WEBPUSH_P256DH');
        $this->auth = (string) $this->getEnv('WEBPUSH_AUTH');

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
            WebPushPingPong::class,
            fn ($app) => new WebPushPingPong(
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

    private function createSubscription(string $endpoint): WebPushSubscription
    {
        return WebPushSubscription::create([
            'endpoint' => $endpoint,
            'p256dh' => $this->p256dh,
            'auth' => $this->auth,
            'browser' => 'chrome',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => (string) $this->user->getKey(),
            'last_seen_at' => now()->subDay(),
        ]);
    }

    private function createConfig(): UniqueTaskConfigRecord
    {
        return new UniqueTaskConfigRecord(
            description: new DescriptionVO('Test ping and prune webpush'),
            scheduled_at: new Iso8601DateTimeVO(now()->subHours(2)->toIso8601String()),
            max_attempts: new MaxFailedAttemptsVO(3),
            grace_period: new DurationVO(3600),
        );
    }

    public function test_register_and_execute_ping_success(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $subscription = $this->createSubscription($this->endpoint);

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneWebPushSubscriptionTask::class),
            StrictDataObject::from(['subscription_id' => (string) $subscription->id]),
            $this->createConfig(),
        );

        $this->processTasks();

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::COMPLETED, $taskModel->getStatus());

        $this->assertDatabaseHas('web_push_subscriptions', ['id' => $subscription->id]);
    }

    public function test_task_deletes_subscription_when_invalid(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $subscription = $this->createSubscription(
            'https://jmt17.google.com/fcm/send/'.str_repeat('a', 200),
        );
        $subscriptionId = $subscription->id;

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneWebPushSubscriptionTask::class),
            StrictDataObject::from(['subscription_id' => (string) $subscription->id]),
            $this->createConfig(),
        );

        $this->processTasks();

        $this->assertDatabaseMissing('web_push_subscriptions', ['id' => $subscriptionId]);
    }

    public function test_task_deletes_subscription_when_config_is_missing(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $this->app['config']->set('notification.channels.webpush.enabled', false);

        $subscription = $this->createSubscription($this->endpoint);
        $subscriptionId = $subscription->id;

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneWebPushSubscriptionTask::class),
            StrictDataObject::from(['subscription_id' => (string) $subscription->id]),
            $this->createConfig(),
        );

        $this->processTasks();

        $this->assertDatabaseMissing('web_push_subscriptions', ['id' => $subscriptionId]);
    }

    public function test_task_fails_when_subscription_not_found(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneWebPushSubscriptionTask::class),
            StrictDataObject::from(['subscription_id' => '01a0de28-605a-7208-8b40-11d0888080f6']),
            $this->createConfig(),
        );

        $this->processTasks();

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
    }

    public function test_task_fails_when_subscription_id_missing(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneWebPushSubscriptionTask::class),
            StrictDataObject::from([]),
            $this->createConfig(),
        );

        $this->processTasks();

        $taskModel = $this->uniqueTaskRepository->findByAlias($alias);
        $this->assertNotNull($taskModel);
        $this->assertEquals(UniqueTaskStatus::FAILED, $taskModel->getStatus());
    }

    public function test_task_deletes_subscription_when_ping_returns_invalid(): void
    {
        $frozenNow = Carbon::create(2026, 6, 23, 12, 0, 0);
        Carbon::setTestNow($frozenNow);

        // Endpoint invalide : le ping échoue, la souscription est supprimée.
        $subscription = $this->createSubscription(
            'https://jmt17.google.com/fcm/send/invalid-endpoint',
        );
        $subscriptionId = $subscription->id;

        $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneWebPushSubscriptionTask::class),
            StrictDataObject::from(['subscription_id' => (string) $subscription->id]),
            $this->createConfig(),
        );

        $this->processTasks();

        $this->assertDatabaseMissing('web_push_subscriptions', ['id' => $subscriptionId]);
    }
}
