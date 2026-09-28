<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Contracts\Repositories\WebPushSubscriptionRepositoryInterface;
use AndyDefer\LaravelNotification\Datas\WebPushSubscriptionData;
use AndyDefer\LaravelNotification\Records\RegisterWebPushSubscriptionRecord;
use AndyDefer\LaravelNotification\Records\WebPushSubscriptionRecord;
use AndyDefer\Nemesis\Enums\ErrorCode;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles the registration of a Web Push subscription for the authenticated user.
 *
 * The action upserts the subscription based on its endpoint, linking it to the
 * currently authenticated notifiable model. It returns the persisted subscription
 * as a {@see WebPushSubscriptionData} payload.
 */
final class RegisterWebPushSubscriptionAction extends AbstractAction
{
    /**
     * @param  NemesisHelper  $helper  Provides access to the current authenticated notifiable
     * @param  WebPushSubscriptionRepositoryInterface  $subscriptions  Persistence layer for Web Push subscriptions
     */
    public function __construct(
        private readonly NemesisHelper $helper,
        private readonly WebPushSubscriptionRepositoryInterface $subscriptions,
    ) {}

    /**
     * Register or update the Web Push subscription for the authenticated notifiable.
     *
     * @param  AbstractRecord  $request  Must be an instance of {@see RegisterWebPushSubscriptionRecord}
     * @return ResponseFactory JSON response containing the persisted subscription
     */
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var RegisterWebPushSubscriptionRecord $request */
        if (! $this->helper->isAuthenticated()) {
            return ErrorCode::MISSING_TOKEN->toJsonResponseFactory();
        }

        $user = $this->helper->getCurrentAuthenticatable();

        if (! $user instanceof Model) {
            return ErrorCode::AUTHENTICATABLE_NOT_FOUND->toJsonResponseFactory();
        }

        $subscription = $this->subscriptions->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => $request->endpoint,
            'p256dh' => $request->p256dh,
            'auth' => $request->auth,
            'browser' => $request->browser,
            'user_agent' => $request->user_agent,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        return ResponseFactory::json(
            WebPushSubscriptionData::from([
                'id' => $subscription->id,
                'endpoint' => $subscription->endpoint,
                'browser' => $subscription->browser,
                'lastSeenAt' => $subscription->last_seen_at?->toIso8601String(),
            ]),
        );
    }
}
