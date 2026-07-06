<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tasks;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Task\Abstract\AbstractRecurringTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;

/**
 * Task for sending recurring notifications to notifiable entities.
 *
 * This task retrieves a notifiable model (User, etc.) and sends a notification
 * using the NotificationSenderProcessor at regular intervals.
 */
final class SendRecurringNotificationTask extends AbstractRecurringTask
{
    /**
     * Validate the payload before execution.
     *
     * @param  StrictDataObject  $payload  The task payload
     *
     * @throws InvalidArgumentException If the payload is invalid
     */
    protected function before(StrictDataObject $payload): void
    {
        $record = NotificationTaskPayloadRecord::from($payload);

        if (empty($record->notifiable_type)) {
            throw new InvalidArgumentException('Notifiable type is required');
        }

        if (empty($record->notifiable_id)) {
            throw new InvalidArgumentException('Notifiable ID is required');
        }

        if (empty($record->body)) {
            throw new InvalidArgumentException('Notification body is required');
        }

        if (empty($record->subject)) {
            throw new InvalidArgumentException('Notification subject is required');
        }

        if (empty($record->channels)) {
            throw new InvalidArgumentException('At least one notification channel is required');
        }

        if (isset($payload->interval_seconds) && $payload->interval_seconds < 60) {
            throw new InvalidArgumentException('Interval must be at least 60 seconds for recurring notifications');
        }
    }

    /**
     * Execute the main business logic.
     *
     * @throws RuntimeException If the notifiable entity is not found
     */
    protected function process(): void
    {
        $payload = NotificationTaskPayloadRecord::from($this->context->getPayload());

        $notifiable = $this->findNotifiable($payload);

        $message = $this->createNotificationMessage($payload);
        $processRecord = $this->createProcessRecord($payload);

        $this->sendNotification($notifiable, $message, $processRecord);

        $this->info(new DescriptionVO(sprintf(
            'Recurring notification sent to %s #%d',
            $payload->notifiable_type,
            $payload->notifiable_id
        )));
    }

    /**
     * Hook executed after the main processing.
     *
     * @param  bool  $success  Indicates whether the task completed successfully
     * @param  DescriptionVO|null  $error  Error description when task failed
     */
    protected function after(bool $success, ?DescriptionVO $error = null): void
    {
        $payload = NotificationTaskPayloadRecord::from($this->context->getPayload());

        if ($success) {
            $this->info(new DescriptionVO(sprintf(
                'Recurring notification task completed for %s #%d',
                $payload->notifiable_type,
                $payload->notifiable_id
            )));
        } else {
            $this->error(new DescriptionVO(sprintf(
                'Recurring notification task failed for %s #%d: %s',
                $payload->notifiable_type,
                $payload->notifiable_id,
                $error?->getValue() ?? 'Unknown error'
            )));

            // Log the failure with structured logging
            $logPayload = StrictDataObject::from([
                'event' => 'recurring_notification_task_failed',
                'notifiable_type' => $payload->notifiable_type,
                'notifiable_id' => $payload->notifiable_id,
                'error' => $error?->getValue(),
                'alias' => $this->context->getAlias()->getValue(),
            ]);

            $this->logger->error(new LogDataRecord(
                type: 'recurring_notification_task',
                payload: $logPayload
            ));
        }
    }

    /**
     * Find the notifiable entity.
     *
     * @param  NotificationTaskPayloadRecord  $payload  The task payload
     * @return object The notifiable entity
     *
     * @throws RuntimeException If the notifiable entity is not found
     */
    private function findNotifiable(NotificationTaskPayloadRecord $payload): object
    {
        /** @var class-string $notifiableType */
        $notifiableType = $payload->notifiable_type;
        $notifiable = $notifiableType::find($payload->notifiable_id);

        if (! $notifiable) {
            throw new RuntimeException(sprintf(
                'Notifiable not found: %s #%d',
                $payload->notifiable_type,
                $payload->notifiable_id
            ));
        }

        return $notifiable;
    }

    /**
     * Create the notification message.
     *
     * @param  NotificationTaskPayloadRecord  $payload  The task payload
     * @return NotificationMessageVO The notification message
     */
    private function createNotificationMessage(NotificationTaskPayloadRecord $payload): NotificationMessageVO
    {
        return new NotificationMessageVO(
            body: $payload->body,
            subject: $payload->subject,
            type: $payload->type,
            data: $payload->data,
        );
    }

    /**
     * Create the process record.
     *
     * @param  NotificationTaskPayloadRecord  $payload  The task payload
     * @return ProcessNotificationRecord The process record
     */
    private function createProcessRecord(NotificationTaskPayloadRecord $payload): ProcessNotificationRecord
    {
        return new ProcessNotificationRecord(
            channels: $payload->channels,
            limit_per_channel: $payload->limit_per_channel,
        );
    }

    /**
     * Send the notification.
     *
     * @param  object  $notifiable  The notifiable entity
     * @param  NotificationMessageVO  $message  The notification message
     * @param  ProcessNotificationRecord  $processRecord  The process record
     */
    private function sendNotification(
        object $notifiable,
        NotificationMessageVO $message,
        ProcessNotificationRecord $processRecord
    ): void {
        /** @var NotificationSenderProcessor $processor */
        $processor = $this->getApplication()->make(NotificationSenderProcessor::class);
        $processor->send($notifiable, $message, $processRecord);
    }

    /**
     * Get the Laravel application instance.
     *
     * @return Application The application container
     */
    private function getApplication(): Application
    {
        return $this->context->getLaravelApp();
    }
}
