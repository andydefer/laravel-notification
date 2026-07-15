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

final class SendRecurringNotificationTask extends AbstractRecurringTask
{
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

        if ($record->channels->isEmpty()) {
            throw new InvalidArgumentException('At least one notification channel is required');
        }

        // ✅ Vérifier l'intervalle depuis le contexte de la tâche récurrente
        $intervalSeconds = $this->context->getIntervalSeconds();

        if ($intervalSeconds !== null && $intervalSeconds->getValue() < 60) {
            throw new InvalidArgumentException('Interval must be at least 60 seconds for recurring notifications');
        }
    }

    protected function process(): void
    {
        $payload = NotificationTaskPayloadRecord::from($this->context->getPayload());

        $notifiable = $this->findNotifiable($payload);

        $message = $this->createNotificationMessage($payload);
        $processRecord = $this->createProcessRecord($payload);

        $this->sendNotification(
            $notifiable,
            $message,
            $processRecord,
            $payload->destination_filter?->toArray()
        );

        $this->info(new DescriptionVO(sprintf(
            'Recurring notification sent to %s #%d',
            $payload->notifiable_type,
            $payload->notifiable_id
        )));
    }

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

    private function createNotificationMessage(NotificationTaskPayloadRecord $payload): NotificationMessageVO
    {
        return new NotificationMessageVO(
            body: $payload->body,
            subject: $payload->subject,
            type: $payload->type,
            data: $payload->data,
        );
    }

    private function createProcessRecord(NotificationTaskPayloadRecord $payload): ProcessNotificationRecord
    {
        return new ProcessNotificationRecord(
            channels: $payload->channels,
            limit_per_channel: $payload->limit_per_channel,
        );
    }

    private function sendNotification(
        object $notifiable,
        NotificationMessageVO $message,
        ProcessNotificationRecord $processRecord,
        ?array $destinationFilters
    ): void {
        /** @var NotificationSenderProcessor $processor */
        $processor = $this->getApplication()->make(NotificationSenderProcessor::class);
        $processor->send($notifiable, $message, $processRecord, $destinationFilters);
    }

    private function getApplication(): Application
    {
        return $this->context->getLaravelApp();
    }
}
