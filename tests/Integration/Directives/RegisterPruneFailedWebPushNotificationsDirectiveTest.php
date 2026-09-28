<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Directives\RegisterPruneFailedWebPushNotificationsDirective;
use AndyDefer\LaravelNotification\Tasks\PruneFailedWebPushNotificationsTask;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Task\Contracts\Repositories\RecurringTaskRepositoryInterface;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Enums\RecurringTaskStatus;
use AndyDefer\Task\Models\RecurringTask;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\Records\RecurringTaskFiltersRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class RegisterPruneFailedWebPushNotificationsDirectiveTest extends TestCase
{
    use RefreshDatabase;

    private DirectiveTestingService $directiveService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directiveService = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: [],
        );

        $this->directiveService
            ->getKernel()
            ->addDirective(RegisterPruneFailedWebPushNotificationsDirective::class);
    }

    protected function tearDown(): void
    {
        $this->directiveService->destroy();
        parent::tearDown();
    }

    // ==================== REGISTRATION ====================

    public function test_registers_task_when_absent(): void
    {
        $response = $this->directiveService->run('notification:register-prune-webpush');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Recurring task registered', $response->output);

        $this->assertRecurringTaskCount(1);
    }

    public function test_does_not_register_twice_without_force(): void
    {
        $this->directiveService->run('notification:register-prune-webpush');
        $response = $this->directiveService->run('notification:register-prune-webpush');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('already registered', $response->output);
        $this->assertRecurringTaskCount(1);
    }

    public function test_accepts_custom_interval_and_attempts(): void
    {
        $response = $this->directiveService->run('notification:register-prune-webpush 900 5');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertRecurringTaskCount(1);
    }

    // ==================== VALIDATION ====================

    public function test_rejects_interval_below_minimum(): void
    {
        $response = $this->directiveService->run('notification:register-prune-webpush 30 3');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertStringContainsString('Interval must be at least 60 seconds', $response->output);
        $this->assertRecurringTaskCount(0);
    }

    public function test_rejects_max_attempts_below_one(): void
    {
        $response = $this->directiveService->run('notification:register-prune-webpush 1800 00');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertStringContainsString('maxAttempts must be at least 1', $response->output);
        $this->assertRecurringTaskCount(0);
    }

    // ==================== ALIASES ====================

    public function test_alias_rpwp_works(): void
    {
        $response = $this->directiveService->run('notification:rpwp');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertRecurringTaskCount(1);
    }

    public function test_alias_n_rpwp_works(): void
    {
        $response = $this->directiveService->run('n:rpwp');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertRecurringTaskCount(1);
    }

    // ==================== DEDUPLICATION ====================

    public function test_deduplicates_existing_tasks_before_registering(): void
    {
        $this->createRawRecurringTask(RecurringTaskStatus::PLAYING);
        $this->createRawRecurringTask(RecurringTaskStatus::PLAYING);
        $this->createRawRecurringTask(RecurringTaskStatus::PAUSED);

        $this->assertRecurringTaskCount(3);

        $response = $this->directiveService->run('notification:register-prune-webpush');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deduplicated 2 task(s)', $response->output);

        $this->assertRecurringTaskCount(1);
        $this->assertSingleTaskIsPlaying();
    }

    public function test_deduplicates_and_registers_with_force(): void
    {
        $this->createRawRecurringTask(RecurringTaskStatus::PLAYING);
        $this->createRawRecurringTask(RecurringTaskStatus::PLAYING);

        $this->assertRecurringTaskCount(2);

        $response = $this->directiveService->run('notification:register-prune-webpush --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deduplicated 1 task(s)', $response->output);
        $this->assertStringContainsString('Recurring task registered', $response->output);

        $this->assertRecurringTaskCount(2);
    }

    public function test_deduplication_keeps_playing_task_when_mixed(): void
    {
        $this->createRawRecurringTask(RecurringTaskStatus::PAUSED);
        $this->createRawRecurringTask(RecurringTaskStatus::PLAYING);

        $response = $this->directiveService->run('notification:register-prune-webpush');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertRecurringTaskCount(1);
        $this->assertSingleTaskIsPlaying();
    }

    // ==================== HELPERS ====================

    private function assertSingleTaskIsPlaying(): void
    {
        /** @var RecurringTaskRepositoryInterface $repository */
        $repository = $this->app->make(RecurringTaskRepositoryInterface::class);

        $tasks = $repository->findBy(new FindByRecord(
            filters: RecurringTaskFiltersRecord::from([
                'fqcn' => new RecurringTaskFqcnVO(PruneFailedWebPushNotificationsTask::class),
                'include_deleted' => true,
            ]),
        ));

        $this->assertSame(1, $tasks->count());
        $this->assertSame(
            RecurringTaskStatus::PLAYING->value,
            $tasks->first()->getStatus()->value,
        );
    }

    private function createRawRecurringTask(RecurringTaskStatus $status): void
    {
        /** @var RecurringTaskServiceInterface $service */
        $service = $this->app->make(RecurringTaskServiceInterface::class);

        $config = RecurringTaskConfigRecord::from([
            'description' => new DescriptionVO('Seed task'),
            'interval_seconds' => new DurationVO(1800),
            'start_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
            'max_attempts' => new MaxFailedAttemptsVO(3),
        ]);

        $alias = $service->register(
            new RecurringTaskFqcnVO(PruneFailedWebPushNotificationsTask::class),
            StrictDataObject::from(['enabled' => true]),
            $config,
        );

        RecurringTask::query()
            ->where('alias', $alias->getValue())
            ->update(['status' => $status->value]);
    }

    private function assertRecurringTaskCount(int $expected): void
    {
        /** @var RecurringTaskRepositoryInterface $repository */
        $repository = $this->app->make(RecurringTaskRepositoryInterface::class);

        $tasks = $repository->findBy(new FindByRecord(
            filters: RecurringTaskFiltersRecord::from([
                'fqcn' => new RecurringTaskFqcnVO(PruneFailedWebPushNotificationsTask::class),
                'include_deleted' => true,
            ]),
        ));

        $this->assertSame(
            $expected,
            $tasks->count(),
            sprintf('Expected %d recurring task(s), got %d', $expected, $tasks->count()),
        );
    }
}
