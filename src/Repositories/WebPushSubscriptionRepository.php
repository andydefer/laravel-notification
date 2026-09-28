<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Repositories;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Contracts\Repositories\WebPushSubscriptionRepositoryInterface;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\LaravelNotification\Records\WebPushSubscriptionFilterRecord;
use AndyDefer\LaravelNotification\Records\WebPushSubscriptionRecord;
use AndyDefer\Repository\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;

final class WebPushSubscriptionRepository extends AbstractRepository implements WebPushSubscriptionRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(WebPushSubscription::class, WebPushSubscriptionRecord::class);
    }

    /**
     * {@inheritDoc}
     */
    public function upsertFor(WebPushSubscriptionRecord $record): WebPushSubscription
    {
        // Endpoint = identifiant fonctionnel unique de la souscription.
        $existing = $this->model->newQuery()
            ->where('endpoint', $record->endpoint)
            ->first();

        if ($existing !== null) {
            $existing->fill([
                'notifiable_type' => $record->notifiable_type,
                'notifiable_id' => $record->notifiable_id,
                'p256dh' => $record->p256dh,
                'auth' => $record->auth,
                'browser' => $record->browser,
                'user_agent' => $record->user_agent,
                'last_seen_at' => $record->last_seen_at ?? now(),
            ]);
            $existing->save();

            return $existing;
        }

        return $this->model->newQuery()->create([
            'endpoint' => $record->endpoint,
            'p256dh' => $record->p256dh,
            'auth' => $record->auth,
            'browser' => $record->browser,
            'user_agent' => $record->user_agent,
            'last_seen_at' => $record->last_seen_at ?? now(),
            'notifiable_type' => $record->notifiable_type,
            'notifiable_id' => $record->notifiable_id,
        ]);
    }

    protected function applyFilters(Builder $query, AbstractRecord $filters): void
    {
        if (! $filters instanceof WebPushSubscriptionFilterRecord) {
            return;
        }

        if ($filters->endpoint !== null) {
            $query->where('endpoint', $filters->endpoint);
        }

        if ($filters->browser !== null) {
            $query->where('browser', $filters->browser);
        }

        if ($filters->notifiable_type !== null) {
            $query->where('notifiable_type', $filters->notifiable_type);
        }

        if ($filters->notifiable_id !== null) {
            $query->where('notifiable_id', $filters->notifiable_id);
        }

        if ($filters->from_last_seen_at !== null) {
            $query->where('last_seen_at', '>=', $filters->from_last_seen_at);
        }

        if ($filters->to_last_seen_at !== null) {
            $query->where('last_seen_at', '<=', $filters->to_last_seen_at);
        }
    }
}
