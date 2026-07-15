<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Services;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Contracts\Processors\NotificationSenderProcessorInterface;
use AndyDefer\LaravelNotification\Contracts\Repositories\NotificationRepositoryInterface;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;
use AndyDefer\LaravelNotification\Records\SendAtRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\Records\SessionStatsRecord;
use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationStatsVO;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class NotificationService implements NotificationServiceInterface
{
    private ?SendOptions $pendingOptions = null;

    public function __construct(
        private readonly NotificationRepositoryInterface $notificationRepository,
        private readonly NotificationSenderProcessorInterface $senderProcessor,
        private readonly UniqueTaskServiceInterface $uniqueTaskService,
        private readonly RecurringTaskServiceInterface $recurringTaskService,
        private readonly LoggerInterface $logger,
        private readonly HydrationService $hydration,
    ) {}

    /**
     * Set options for the next send operation.
     *
     * @param  SendOptions  $options  The send options
     */
    public function withOptions(SendOptions $options): self
    {
        $this->pendingOptions = $options;

        return $this;
    }

    /**
     * Reset the pending options.
     */
    public function resetOptions(): self
    {
        $this->pendingOptions = null;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function sendNow(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        ?SendNowRecord $record = null
    ): SendResultCollection {
        $record = $record ?? new SendNowRecord;
        $options = $this->pendingOptions ?? new SendOptions;
        $this->resetOptions();

        $processRecord = new ProcessNotificationRecord(
            channels: $options->channels ?? $record->channels,
            limit_per_channel: $options->limitPerChannel ?? $record->limit_per_channel,
        );

        $this->logInfo('Sending notification immediately', [
            'notifiable' => $notifiable->getMorphClass().'#'.$notifiable->getKey(),
            'message_type' => $message->getType(),
            'channels' => $processRecord->channels->isNotEmpty() ? $processRecord->channels->getChannelClasses() : 'all',
            'limit_per_channel' => $processRecord->limit_per_channel,
            'destination_filters' => $options->destinationFilters?->toArray(),
        ]);

        return $this->senderProcessor->send(
            $notifiable,
            $message,
            $processRecord,
            $options->destinationFilters?->toArray()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function sendLater(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        ?SendLaterRecord $record = null
    ): TaskAliasVO {
        $record = $record ?? new SendLaterRecord(delay_seconds: 60);

        if ($record->delay_seconds <= 0) {
            throw new InvalidArgumentException('Delay seconds must be greater than 0.');
        }

        $options = $this->pendingOptions ?? new SendOptions;
        $this->resetOptions();

        $payload = $this->createTaskPayload($notifiable, $message, $record, $options);

        $scheduledAt = new NotificationDateTimeVO(
            Carbon::now()->addSeconds($record->delay_seconds)->toIso8601String()
        );

        $config = UniqueTaskConfigRecord::from([
            'description' => 'Delayed notification: '.$message->getSubject(),
            'scheduled_at' => new Iso8601DateTimeVO($scheduledAt->getValue()),
            'max_attempts' => 3,
            'grace_period' => 86400,
        ]);

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            StrictDataObject::from($payload->toArray()),
            $config
        );

        $this->logInfo('Delayed notification scheduled', [
            'alias' => $alias->getValue(),
            'scheduled_at' => $scheduledAt->getValue(),
            'destination_filters' => $options->destinationFilters?->toArray(),
        ]);

        return $alias;
    }

    /**
     * {@inheritDoc}
     */
    public function sendAt(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        ?SendAtRecord $record = null
    ): TaskAliasVO {
        $record = $record ?? new SendAtRecord(
            scheduled_at: new NotificationDateTimeVO(Carbon::now()->addDay()->toIso8601String())
        );

        $now = new NotificationDateTimeVO(Carbon::now()->toIso8601String());

        if ($record->scheduled_at->isBeforeOrEqual($now)) {
            throw new InvalidArgumentException('Scheduled date must be in the future.');
        }

        $options = $this->pendingOptions ?? new SendOptions;
        $this->resetOptions();

        $payload = $this->createTaskPayload($notifiable, $message, $record, $options);

        $config = UniqueTaskConfigRecord::from([
            'description' => 'Scheduled notification: '.$message->getSubject(),
            'scheduled_at' => new Iso8601DateTimeVO($record->scheduled_at->getValue()),
            'max_attempts' => 3,
            'grace_period' => 86400,
        ]);

        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            StrictDataObject::from($payload->toArray()),
            $config
        );

        $this->logInfo('Scheduled notification created', [
            'alias' => $alias->getValue(),
            'scheduled_at' => $record->scheduled_at->getValue(),
            'destination_filters' => $options->destinationFilters?->toArray(),
        ]);

        return $alias;
    }

    /**
     * {@inheritDoc}
     */
    public function sendRecurring(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        ?SendRecurringRecord $record = null
    ): TaskAliasVO {
        $record = $record ?? new SendRecurringRecord(
            interval_seconds: 86400,
            start_at: new NotificationDateTimeVO(Carbon::now()->toIso8601String())
        );

        if ($record->interval_seconds < 1) {
            throw new InvalidArgumentException('Interval seconds must be at least 1 second.');
        }

        $options = $this->pendingOptions ?? new SendOptions;
        $this->resetOptions();

        $payload = $this->createTaskPayload($notifiable, $message, $record, $options);

        $config = RecurringTaskConfigRecord::from([
            'description' => 'Recurring notification: '.$message->getSubject(),
            'interval_seconds' => $record->interval_seconds,
            'start_at' => new Iso8601DateTimeVO($record->start_at->getValue()),
            'end_at' => $record->end_at ? new Iso8601DateTimeVO($record->end_at->getValue()) : null,
            'max_attempts' => $record->max_attempts?->getValue() ?? 3,
        ]);

        $alias = $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            StrictDataObject::from($payload->toArray()),
            $config
        );

        $this->logInfo('Recurring notification scheduled', [
            'alias' => $alias->getValue(),
            'interval_seconds' => $record->interval_seconds,
            'start_at' => $record->start_at->getValue(),
            'end_at' => $record->end_at?->getValue(),
            'destination_filters' => $options->destinationFilters?->toArray(),
        ]);

        return $alias;
    }

    /**
     * {@inheritDoc}
     */
    public function cancel(string $signature): bool
    {
        try {
            $alias = new TaskAliasVO($signature);

            if ($this->recurringTaskService->exists($alias)) {
                $this->recurringTaskService->cancel($alias);
                $this->logInfo('Recurring notification cancelled', ['signature' => $signature]);

                return true;
            }

            if ($this->uniqueTaskService->exists($alias)) {
                $this->uniqueTaskService->cancel($alias);
                $this->logInfo('Unique notification cancelled', ['signature' => $signature]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            $this->logWarning('Failed to cancel notification', [
                'signature' => $signature,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function pause(string $signature): bool
    {
        try {
            $alias = new TaskAliasVO($signature);

            if (! $this->recurringTaskService->exists($alias)) {
                return false;
            }

            $this->recurringTaskService->pause($alias);
            $this->logInfo('Recurring notification paused', ['signature' => $signature]);

            return true;
        } catch (\Exception $e) {
            $this->logWarning('Failed to pause recurring notification', [
                'signature' => $signature,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function resume(string $signature): bool
    {
        try {
            $alias = new TaskAliasVO($signature);

            if (! $this->recurringTaskService->exists($alias)) {
                return false;
            }

            $this->recurringTaskService->resume($alias);
            $this->logInfo('Recurring notification resumed', ['signature' => $signature]);

            return true;
        } catch (\Exception $e) {
            $this->logWarning('Failed to resume recurring notification', [
                'signature' => $signature,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function changeInterval(string $signature, int $newIntervalSeconds): bool
    {
        if ($newIntervalSeconds < 1) {
            throw new InvalidArgumentException('Interval seconds must be at least 1 second.');
        }

        try {
            $alias = new TaskAliasVO($signature);

            if (! $this->recurringTaskService->exists($alias)) {
                return false;
            }

            $this->recurringTaskService->changeInterval(
                $alias,
                new DurationVO($newIntervalSeconds)
            );
            $this->logInfo('Recurring notification interval changed', [
                'signature' => $signature,
                'new_interval' => $newIntervalSeconds,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logWarning('Failed to change interval for recurring notification', [
                'signature' => $signature,
                'new_interval' => $newIntervalSeconds,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(NotifiableInterface&Model $notifiable): NotificationStatsVO
    {
        $total = $this->notificationRepository->countByNotifiable($notifiable);
        $sent = $this->notificationRepository->countByStatus($notifiable, NotificationStatus::SENT);
        $failed = $this->notificationRepository->countByStatus($notifiable, NotificationStatus::FAILED);
        $delivered = $this->notificationRepository->countByStatus($notifiable, NotificationStatus::DELIVERED);
        $pending = $this->notificationRepository->countByStatus($notifiable, NotificationStatus::PENDING);
        $successRate = $total > 0 ? round(($sent / $total) * 100, 2) : 0;

        return new NotificationStatsVO(
            total: $total,
            sent: $sent,
            failed: $failed,
            delivered: $delivered,
            pending: $pending,
            success_rate: $successRate,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getSessionStats(string $sessionId): SessionStatsRecord
    {
        $total = $this->notificationRepository->countBySession($sessionId);
        $sent = $this->notificationRepository->findBySession($sessionId)
            ->where('status', NotificationStatus::SENT->value)
            ->count();
        $failed = $this->notificationRepository->findBySession($sessionId)
            ->where('status', NotificationStatus::FAILED->value)
            ->count();
        $pending = $this->notificationRepository->findBySession($sessionId)
            ->where('status', NotificationStatus::PENDING->value)
            ->count();

        return new SessionStatsRecord(
            session_id: $sessionId,
            total: $total,
            sent: $sent,
            failed: $failed,
            pending: $pending,
        );
    }

    /**
     * Create the task payload with options.
     *
     * @param  NotifiableInterface&Model  $notifiable  The notifiable entity
     * @param  NotificationMessageVO  $message  The notification message
     * @param  object  $record  The schedule record
     * @param  SendOptions  $options  The send options
     * @return NotificationTaskPayloadRecord The task payload
     */
    private function createTaskPayload(
        NotifiableInterface&Model $notifiable,
        NotificationMessageVO $message,
        object $record,
        SendOptions $options
    ): NotificationTaskPayloadRecord {
        $channels = $options->channels ?? $record->channels ?? new FqcnChannelCollection;
        $limitPerChannel = $options->limitPerChannel ?? $record->limit_per_channel ?? null;

        return new NotificationTaskPayloadRecord(
            notifiable_type: $notifiable->getMorphClass(),
            notifiable_id: $notifiable->getKey(),
            body: $message->getBody(),
            subject: $message->getSubject(),
            type: $message->getType(),
            data: $message->getData(),
            channels: $channels,
            limit_per_channel: $limitPerChannel,
            destination_filter: $options->destinationFilters,
        );
    }

    /**
     * Log an info message.
     *
     * @param  string  $message  The log message
     * @param  array<string, mixed>  $context  The log context
     */
    private function logInfo(string $message, array $context = []): void
    {
        $this->logger->info(new LogDataRecord(
            type: 'notification',
            payload: $this->hydration->hydrate(StrictDataObject::class, [
                'event' => $message,
                'context' => $context,
            ])
        ));
    }

    /**
     * Log a warning message.
     *
     * @param  string  $message  The log message
     * @param  array<string, mixed>  $context  The log context
     */
    private function logWarning(string $message, array $context = []): void
    {
        $this->logger->warning(new LogDataRecord(
            type: 'notification',
            payload: $this->hydration->hydrate(StrictDataObject::class, [
                'event' => $message,
                'context' => $context,
            ])
        ));
    }
}
