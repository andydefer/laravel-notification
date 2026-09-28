<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Repositories;

use AndyDefer\LaravelNotification\Contracts\Repositories\WebPushSubscriptionRepositoryInterface;
use AndyDefer\LaravelNotification\Enums\WebPushBrowser;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\LaravelNotification\Records\WebPushSubscriptionFilterRecord;
use AndyDefer\LaravelNotification\Records\WebPushSubscriptionRecord;
use AndyDefer\LaravelNotification\Repositories\WebPushSubscriptionRepository;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;

final class WebPushSubscriptionRepositoryTest extends TestCase
{
    private const ENDPOINT = 'https://jmt17.google.com/fcm/send/c9ptagJdvGY:APA91bGf2EgcwcSU-3hJzsCxZSXEa8fcC_47jcH0ZdUyXTj50PjLLO-okUJNTp-5RkmCthbUxKFiHrgO9vugdBR5yvR9hlbmspLzC8xTowtS2zOhKtr6gFMfslHjInaZMCnpripqk6L_';

    private const P256DH = 'BGtKsvWiILk_CxgTlaz1ajvawoh2FtUIiUUIPMyQ_wG6yS-9SwG3bK4KqusuVDmftoZqIz8LnWRl5tJPs053iYI';

    private const AUTH = 'hv_Nwds7IHarbxh2KChgEw';

    private WebPushSubscriptionRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(WebPushSubscriptionRepositoryInterface::class);
    }

    public function test_resolves_interface_from_container(): void
    {
        $this->assertInstanceOf(WebPushSubscriptionRepository::class, $this->repository);
    }

    public function test_upsert_for_creates_new_subscription(): void
    {
        $user = TestUser::create(['name' => 'John']);

        $subscription = $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT,
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'browser' => WebPushBrowser::CHROME->value,
            'user_agent' => 'Mozilla/5.0',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        $this->assertInstanceOf(WebPushSubscription::class, $subscription);
        $this->assertSame(self::ENDPOINT, $subscription->endpoint);
        $this->assertSame(self::P256DH, $subscription->p256dh);
        $this->assertSame(self::AUTH, $subscription->auth);
        $this->assertSame('chrome', $subscription->browser);
        $this->assertNotNull($subscription->last_seen_at);

        $this->assertDatabaseHas('web_push_subscriptions', [
            'endpoint' => self::ENDPOINT,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]);
    }

    public function test_upsert_for_updates_existing_subscription_keys(): void
    {
        $user = TestUser::create(['name' => 'John']);

        $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT,
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        $newP256dh = 'NEW_P256DH_'.str_repeat('x', 80);
        $newAuth = 'NEW_AUTH_'.str_repeat('y', 20);

        $updated = $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT,
            'p256dh' => $newP256dh,
            'auth' => $newAuth,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        $this->assertSame($newP256dh, $updated->p256dh);
        $this->assertSame($newAuth, $updated->auth);
        $this->assertSame(1, WebPushSubscription::count());
    }

    public function test_upsert_for_reassigns_subscription_to_another_user(): void
    {
        $userA = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $userB = TestUser::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT,
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'notifiable_type' => $userA->getMorphClass(),
            'notifiable_id' => (string) $userA->getKey(),
        ]));

        $reassigned = $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT,
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'notifiable_type' => $userB->getMorphClass(),
            'notifiable_id' => (string) $userB->getKey(),
        ]));

        $this->assertSame((string) $userB->getKey(), $reassigned->notifiable_id);
        $this->assertSame(1, WebPushSubscription::count());
    }

    public function test_upsert_for_keeps_two_distinct_endpoints_for_same_user(): void
    {
        $user = TestUser::create(['name' => 'John']);

        $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT,
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT.'-second',
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        $this->assertSame(2, WebPushSubscription::count());
    }

    public function test_find_by_filters_by_notifiable_id(): void
    {
        $userA = TestUser::create(['name' => 'A']);
        $userB = TestUser::create(['name' => 'B', 'email' => 'b@example.com']);

        $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT,
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'notifiable_type' => $userA->getMorphClass(),
            'notifiable_id' => (string) $userA->getKey(),
        ]));

        $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT.'-b',
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'notifiable_type' => $userB->getMorphClass(),
            'notifiable_id' => (string) $userB->getKey(),
        ]));

        $result = $this->repository->findBy(new FindByRecord(
            filters: WebPushSubscriptionFilterRecord::from([
                'notifiable_id' => (string) $userA->getKey(),
            ]),
            sortBy: new SortColumns('created_at:desc'),
        ));

        $this->assertCount(1, $result);
        $this->assertSame((string) $userA->getKey(), $result->first()->notifiable_id);
    }

    public function test_find_by_filters_by_browser(): void
    {
        $user = TestUser::create(['name' => 'John']);

        $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT,
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'browser' => WebPushBrowser::CHROME->value,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT.'-ff',
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'browser' => WebPushBrowser::FIREFOX->value,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        $result = $this->repository->findBy(new FindByRecord(
            filters: WebPushSubscriptionFilterRecord::from([
                'browser' => WebPushBrowser::FIREFOX->value,
            ]),
        ));

        $this->assertCount(1, $result);
        $this->assertSame('firefox', $result->first()->browser);
    }

    public function test_count_with_filters(): void
    {
        $user = TestUser::create(['name' => 'John']);

        $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT,
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'browser' => WebPushBrowser::CHROME->value,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        $this->repository->upsertFor(WebPushSubscriptionRecord::from([
            'endpoint' => self::ENDPOINT.'-safari',
            'p256dh' => self::P256DH,
            'auth' => self::AUTH,
            'browser' => WebPushBrowser::SAFARI->value,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        $count = $this->repository->count(WebPushSubscriptionFilterRecord::from([
            'browser' => WebPushBrowser::SAFARI->value,
        ]));

        $this->assertSame(1, $count);
    }
}
