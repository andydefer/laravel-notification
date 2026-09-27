<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelNotification\Directives\RegisterPruneFailedFcmNotificationsDirective;
use AndyDefer\LaravelNotification\Tasks\PruneFailedFcmNotificationsTask;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
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

    public function test_registers_task_when_absent(): void
    {
        $response = $this->directiveService->run('notification:register-prune-fcm');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Recurring task registered', $response->output);

        $this->assertRecurringTaskExists();
    }

    public function test_does_not_register_twice_without_force(): void
    {
        $this->directiveService->run('notification:register-prune-fcm');
        $response = $this->directiveService->run('notification:register-prune-fcm');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('already registered', $response->output);
        $this->assertRecurringTaskCount(1);
    }

    public function test_force_registers_duplicate(): void
    {
        $this->directiveService->run('notification:register-prune-fcm');
        $response = $this->directiveService->run('notification:register-prune-fcm --force');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Recurring task registered', $response->output);
        $this->assertRecurringTaskCount(2);
    }

    public function test_accepts_custom_interval_and_attempts(): void
    {
        $response = $this->directiveService->run('notification:register-prune-fcm 900 5');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertRecurringTaskExists();
    }

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

    public function test_alias_rpf_works(): void
    {
        $response = $this->directiveService->run('notification:rpf');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertRecurringTaskExists();
    }

    private function assertRecurringTaskExists(): void
    {
        $this->assertRecurringTaskCount(1);
    }

    private function assertRecurringTaskCount(int $expected): void
    {
        /** @var RecurringTaskServiceInterface $service */
        $service = $this->app->make(RecurringTaskServiceInterface::class);

        $fqcn = new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class);

        $count = collect()
            ->merge($service->findWaiting())
            ->merge($service->findPlaying())
            ->merge($service->findPaused())
            ->filter(static function ($task) use ($fqcn): bool {
                return $task->fqcn !== null
                    && $task->fqcn->getValue() === $fqcn->getValue();
            })
            ->count();

        $this->assertSame(
            $expected,
            $count,
            sprintf('Expected %d recurring task(s), got %d', $expected, $count),
        );
    }
}
