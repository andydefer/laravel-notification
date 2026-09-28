<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tasks;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\WebPushChannel;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\Task\Abstract\AbstractRecurringTask;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recurring task that scans FAILED Web Push notifications, resolves
 * their notifiable, and schedules a PingAndPruneWebPushSubscriptionTask
 * for each subscription owned by that notifiable.
 *
 * The ping/prune work is intentionally delegated to a unique task so
 * the recurring scan stays fast and never blocks the worker on
 * network calls to the push service.
 */
final class PruneFailedWebPushNotificationsTask extends AbstractRecurringTask
{
    private const BATCH_SIZE = 200;

    protected function process(): void
    {
        $this->info(new DescriptionVO('Scanning FAILED Web Push notifications...'));

        /** @var UniqueTaskServiceInterface $uniqueTaskService */
        $uniqueTaskService = $this->context
            ->getLaravelApp()
            ->make(UniqueTaskServiceInterface::class);

        $scheduled = 0;

        Notification::query()
            ->where('channel', WebPushChannel::class)
            ->where('status', NotificationStatus::FAILED->value)
            ->orderBy('created_at')
            ->chunk(self::BATCH_SIZE, function (Collection $notifications) use (&$scheduled, $uniqueTaskService): void {
                foreach ($notifications as $notification) {
                    $notifiable = $notification->notifiable;

                    if ($notifiable === null) {
                        continue;
                    }

                    $subscriptions = WebPushSubscription::query()
                        ->where('notifiable_type', $notifiable->getMorphClass())
                        ->where('notifiable_id', (string) $notifiable->getKey())
                        ->get();

                    foreach ($subscriptions as $subscription) {
                        $this->schedulePingAndPrune($uniqueTaskService, $subscription);
                        $scheduled++;
                    }
                }
            });

        $this->info(new DescriptionVO(
            sprintf('Scheduled %d ping/prune task(s).', $scheduled),
        ));
    }

    private function schedulePingAndPrune(
        UniqueTaskServiceInterface $uniqueTaskService,
        WebPushSubscription $subscription,
    ): void {
        $payload = StrictDataObject::from([
            'subscription_id' => (string) $subscription->id,
        ]);

        $config = UniqueTaskConfigRecord::from([
            'description' => new DescriptionVO(sprintf(
                'Ping and prune Web Push subscription %s',
                $subscription->id,
            )),
            'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
            'max_attempts' => new MaxFailedAttemptsVO(3),
            'grace_period' => new DurationVO(3600),
        ]);

        $uniqueTaskService->register(
            new UniqueTaskFqcnVO(PingAndPruneWebPushSubscriptionTask::class),
            $payload,
            $config,
        );
    }
}
