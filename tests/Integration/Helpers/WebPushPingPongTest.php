<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Helpers;

use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Helpers\WebPushPingPong;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;

final class WebPushPingPongTest extends TestCase
{
    private WebPushPingPong $pingPong;

    private TestUser $user;

    private string $endpoint;

    private string $p256dh;

    private string $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pingPong = app(WebPushPingPong::class);

        $this->endpoint = (string) $this->getEnv('WEBPUSH_ENDPOINT');
        $this->p256dh = (string) $this->getEnv('WEBPUSH_P256DH');
        $this->auth = (string) $this->getEnv('WEBPUSH_AUTH');

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+33123456789',
        ]);
    }

    protected function tearDown(): void
    {
        $this->user->delete();
        parent::tearDown();
    }

    private function makeSubscription(string $endpoint): WebPushSubscription
    {
        return WebPushSubscription::create([
            'endpoint' => $endpoint,
            'p256dh' => $this->p256dh,
            'auth' => $this->auth,
            'browser' => 'chrome',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => (string) $this->user->getKey(),
            'last_seen_at' => now()->subDay(),
        ]);
    }

    public function test_resolves_helper_from_container(): void
    {
        $this->assertInstanceOf(WebPushPingPong::class, $this->pingPong);
    }

    public function test_ping_returns_pong_when_push_service_accepts(): void
    {
        $subscription = $this->makeSubscription($this->endpoint);
        $before = $subscription->last_seen_at;

        $status = $this->pingPong->ping($subscription);

        $this->assertSame(PingStatus::PONG, $status);

        $subscription->refresh();
        $this->assertTrue($subscription->last_seen_at->greaterThan($before));
    }

    public function test_ping_returns_invalid_when_endpoint_is_unregistered(): void
    {
        $subscription = $this->makeSubscription(
            'https://jmt17.google.com/fcm/send/'.str_repeat('a', 200),
        );

        $status = $this->pingPong->ping($subscription);

        $this->assertSame(PingStatus::INVALID, $status);
    }

    public function test_ping_returns_invalid_when_driver_config_is_missing(): void
    {
        $this->app['config']->set('notification.channels.webpush.enabled', false);

        $subscription = $this->makeSubscription($this->endpoint);

        $status = $this->pingPong->ping($subscription);

        $this->assertSame(PingStatus::INVALID, $status);
    }

    public function test_is_alive_returns_true_when_pong(): void
    {
        $subscription = $this->makeSubscription($this->endpoint);

        $this->assertTrue($this->pingPong->isAlive($subscription));
    }

    public function test_is_alive_returns_false_when_invalid(): void
    {
        $subscription = $this->makeSubscription(
            'https://jmt17.google.com/fcm/send/'.str_repeat('a', 200),
        );

        $this->assertFalse($this->pingPong->isAlive($subscription));
    }

    public function test_ping_or_prune_deletes_subscription_when_invalid(): void
    {
        $subscription = $this->makeSubscription(
            'https://jmt17.google.com/fcm/send/'.str_repeat('a', 200),
        );
        $subscriptionId = $subscription->id;

        $status = $this->pingPong->pingOrPrune($subscription);

        $this->assertSame(PingStatus::INVALID, $status);
        $this->assertDatabaseMissing('web_push_subscriptions', ['id' => $subscriptionId]);
    }

    public function test_ping_or_prune_keeps_subscription_when_pong(): void
    {
        $subscription = $this->makeSubscription($this->endpoint);
        $subscriptionId = $subscription->id;

        $status = $this->pingPong->pingOrPrune($subscription);

        $this->assertSame(PingStatus::PONG, $status);
        $this->assertDatabaseHas('web_push_subscriptions', ['id' => $subscriptionId]);
    }

    public function test_ping_or_prune_deletes_subscription_when_config_is_missing(): void
    {
        $this->app['config']->set('notification.channels.webpush.enabled', false);

        $subscription = $this->makeSubscription($this->endpoint);
        $subscriptionId = $subscription->id;

        $status = $this->pingPong->pingOrPrune($subscription);

        $this->assertSame(PingStatus::INVALID, $status);
        $this->assertDatabaseMissing('web_push_subscriptions', ['id' => $subscriptionId]);
    }

    public function test_subscription_ping_method_delegates_to_helper(): void
    {
        $subscription = $this->makeSubscription($this->endpoint);

        $this->assertSame(PingStatus::PONG, $subscription->ping());
    }

    public function test_subscription_is_alive_method_delegates_to_helper(): void
    {
        $subscription = $this->makeSubscription($this->endpoint);

        $this->assertTrue($subscription->isAlive());
    }

    public function test_subscription_ping_or_prune_method_delegates_to_helper(): void
    {
        $subscription = $this->makeSubscription(
            'https://jmt17.google.com/fcm/send/'.str_repeat('a', 200),
        );
        $subscriptionId = $subscription->id;

        $status = $subscription->pingOrPrune();

        $this->assertSame(PingStatus::INVALID, $status);
        $this->assertDatabaseMissing('web_push_subscriptions', ['id' => $subscriptionId]);
    }

    public function test_ping_updates_last_seen_at_only_on_pong(): void
    {
        $invalidSubscription = $this->makeSubscription(
            'https://jmt17.google.com/fcm/send/'.str_repeat('a', 200),
        );
        $before = $invalidSubscription->last_seen_at;

        $this->pingPong->ping($invalidSubscription);

        $invalidSubscription->refresh();
        $this->assertEquals($before->getTimestamp(), $invalidSubscription->last_seen_at->getTimestamp());
    }
}
