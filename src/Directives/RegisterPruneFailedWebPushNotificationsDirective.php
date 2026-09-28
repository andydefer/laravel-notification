<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Tasks\PruneFailedWebPushNotificationsTask;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;

class RegisterPruneFailedWebPushNotificationsDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'notification:register-prune-webpush {interval=1800}#"Interval in seconds"
            {maxAttempts=3}#"Maximum number of attempts"
            {--force}#"Re-register even if the task already exists"';
    }

    public function getDescription(): string
    {
        return 'Register the recurring task that prunes invalid Web Push subscriptions';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from([
            'notification:rpwp',
            'n:rpwp',
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

        /** @var RecurringTaskServiceInterface $service */
        $service = app(RecurringTaskServiceInterface::class);

        $fqcn = new RecurringTaskFqcnVO(PruneFailedWebPushNotificationsTask::class);

        if (! $force && $this->recurringTaskExists($service, $fqcn)) {
            $this->warn(sprintf(
                'Recurring task %s is already registered. Use --force to override.',
                PruneFailedWebPushNotificationsTask::class,
            ));

            return ExitCode::SUCCESS;
        }

        $payload = StrictDataObject::from([
            'enabled' => true,
        ]);

        $config = RecurringTaskConfigRecord::from([
            'description' => new DescriptionVO(
                'Prune invalid Web Push subscriptions from failed notifications',
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

    private function recurringTaskExists(
        RecurringTaskServiceInterface $service,
        RecurringTaskFqcnVO $fqcn,
    ): bool {
        $existing = collect()
            ->merge($service->findWaiting())
            ->merge($service->findPlaying())
            ->merge($service->findPaused())
            ->filter(static function ($task) use ($fqcn): bool {
                return $task->fqcn !== null
                    && $task->fqcn->getValue() === $fqcn->getValue();
            });

        return $existing->isNotEmpty();
    }
}
