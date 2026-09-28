<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Tasks\PruneFailedFcmNotificationsTask;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;
use AndyDefer\Task\Contracts\Repositories\RecurringTaskRepositoryInterface;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Enums\RecurringTaskStatus;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\Records\RecurringTaskFiltersRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;

class RegisterPruneFailedFcmNotificationsDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'notification:register-prune-fcm {interval=1800}#"Interval in seconds"
            {maxAttempts=3}#"Maximum number of attempts"
            {--force}#"Re-register even if the task already exists"';
    }

    public function getDescription(): string
    {
        return 'Register the recurring task that prunes invalid FCM devices';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from([
            'notification:rpf',
            'n:rpf',
        ]);
    }

    protected function beforeExecute(): void
    {
        $interval = (int) $this->getArgument('interval');

        if ($interval < 60) {
            throw new \InvalidArgumentException('Interval must be at least 60 seconds.');
        }

        $maxAttempts = (int) $this->getArgument('maxAttempts');

        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be at least 1.');
        }
    }

    protected function execute(): ExitCode
    {
        $interval = (int) $this->getArgument('interval');
        $maxAttempts = (int) $this->getArgument('maxAttempts');
        $force = $this->getFlag('force');

        $app = $this->getKernel()->getApplication();

        /** @var RecurringTaskServiceInterface $service */
        $service = $app->make(RecurringTaskServiceInterface::class);

        /** @var RecurringTaskRepositoryInterface $repository */
        $repository = $app->make(RecurringTaskRepositoryInterface::class);

        $fqcn = new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class);

        // Toujours dédupliquer avant d'enregistrer.
        $this->unify($repository, $fqcn);

        if (! $force && $this->recurringTaskExists($repository, $fqcn)) {
            $this->warn(sprintf(
                'Recurring task %s is already registered. Use --force to override.',
                PruneFailedFcmNotificationsTask::class,
            ));

            return ExitCode::SUCCESS;
        }

        $payload = StrictDataObject::from([
            'enabled' => true,
        ]);

        $config = RecurringTaskConfigRecord::from([
            'description' => new DescriptionVO(
                'Prune invalid FCM devices from failed notifications',
            ),
            'interval_seconds' => new DurationVO($interval),
            'start_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
            'max_attempts' => new MaxFailedAttemptsVO($maxAttempts),
        ]);

        $alias = $service->register($fqcn, $payload, $config);

        $this->info(sprintf(
            'Recurring task registered: %s',
            $alias->getValue(),
        ));

        return ExitCode::SUCCESS;
    }

    /**
     * Keeps a single task per FQCN and force-deletes every other
     * duplicate, regardless of state. Ensures the directive is
     * idempotent across runs.
     */
    private function unify(
        RecurringTaskRepositoryInterface $repository,
        RecurringTaskFqcnVO $fqcn,
    ): void {
        $tasks = $repository->findBy(new FindByRecord(
            filters: RecurringTaskFiltersRecord::from([
                'fqcn' => $fqcn,
                'include_deleted' => true,
            ]),
            sortBy: new SortColumns('created_at:asc'),
        ));

        if ($tasks->count() <= 1) {
            return;
        }

        $playing = $tasks->filter(
            fn ($task) => $task->getStatus() === RecurringTaskStatus::PLAYING,
        );

        $kept = $playing->first() ?? $tasks->first();
        $deleted = 0;

        foreach ($tasks as $task) {
            if ($task->getId()->getValue() === $kept->getId()->getValue()) {
                continue;
            }

            $repository->forceDelete($task->getId()->getValue());
            $deleted++;
        }

        $this->warn(sprintf(
            'Deduplicated %d task(s) for %s.',
            $deleted,
            PruneFailedFcmNotificationsTask::class,
        ));
    }

    private function recurringTaskExists(
        RecurringTaskRepositoryInterface $repository,
        RecurringTaskFqcnVO $fqcn,
    ): bool {
        $tasks = $repository->findBy(new FindByRecord(
            filters: RecurringTaskFiltersRecord::from([
                'fqcn' => $fqcn,
            ]),
        ));

        return $tasks->isNotEmpty();
    }
}
