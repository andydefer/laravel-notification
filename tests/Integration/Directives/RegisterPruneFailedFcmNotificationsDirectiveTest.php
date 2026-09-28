<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Directives\RegisterPruneFailedFcmNotificationsDirective;
use AndyDefer\LaravelNotification\Tasks\PruneFailedFcmNotificationsTask;
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

final class RegisterPruneFailedFcmNotificationsDirectiveTest extends TestCase
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
            ->addDirective(RegisterPruneFailedFcmNotificationsDirective::class);
    }

    protected function tearDown(): void
    {
        $this->directiveService->destroy();
        parent::tearDown();
    }

    // ==================== REGISTRATION ====================

    public function test_registers_task_when_absent(): void
    {
        $response = $this->directiveService->run('notification:register-prune-fcm');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Recurring task registered', $response->output);

        $this->assertRecurringTaskCount(1);
    }

    public function test_does_not_register_twice_without_force(): void
    {
        $this->directiveService->run('notification:register-prune-fcm');
        $response = $this->directiveService->run('notification:register-prune-fcm');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('already registered', $response->output);
        $this->assertRecurringTaskCount(1);
    }

    public function test_accepts_custom_interval_and_attempts(): void
    {
        $response = $this->directiveService->run('notification:register-prune-fcm 900 5');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertRecurringTaskCount(1);
    }

    // ==================== VALIDATION ====================

    public function test_rejects_interval_below_minimum(): void
    {
        $response = $this->directiveService->run('notification:register-prune-fcm 30 3');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertStringContainsString('Interval must be at least 60 seconds', $response->output);
        $this->assertRecurringTaskCount(0);
    }

    public function test_rejects_max_attempts_below_one(): void
    {
        $response = $this->directiveService->run('notification:register-prune-fcm 1800 00');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertStringContainsString('maxAttempts must be at least 1', $response->output);
        $this->assertRecurringTaskCount(0);
    }

    // ==================== ALIASES ====================

    public function test_alias_rpf_works(): void
    {
        $response = $this->directiveService->run('notification:rpf');

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

        $response = $this->directiveService->run('notification:register-prune-fcm');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deduplicated 2 task(s)', $response->output);

        // Un seul task après dédup, et il est PLAYING.
        $this->assertRecurringTaskCount(1);
        $this->assertSingleTaskIsPlaying();
    }

    public function test_deduplicates_and_registers_with_force(): void
    {
        $this->createRawRecurringTask(RecurringTaskStatus::PLAYING);
        $this->createRawRecurringTask(RecurringTaskStatus::PLAYING);

        $this->assertRecurringTaskCount(2);

        $response = $this->directiveService->run('notification:register-prune-fcm --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Deduplicated 1 task(s)', $response->output);
        $this->assertStringContainsString('Recurring task registered', $response->output);

        // Après dédup (1 reste) puis force (1 nouveau) → 2 tâches.
        $this->assertRecurringTaskCount(2);
    }

    public function test_deduplication_keeps_playing_task_when_mixed(): void
    {
        $paused = $this->createRawRecurringTask(RecurringTaskStatus::PAUSED);
        $playing = $this->createRawRecurringTask(RecurringTaskStatus::PLAYING);

        $response = $this->directiveService->run('notification:register-prune-fcm');

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
                'fqcn' => new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
                'include_deleted' => true,
            ]),
        ));

        $this->assertSame(1, $tasks->count());
        $this->assertSame(
            RecurringTaskStatus::PLAYING->value,
            $tasks->first()->getStatus()->value,
        );
    }

    /**
     * Creates a recurring task directly through the service, with the
     * given status. Used to seed the state for deduplication tests.
     */
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
            new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
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
                'fqcn' => new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
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
