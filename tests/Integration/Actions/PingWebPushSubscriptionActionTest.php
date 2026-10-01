<?php

// tests/Integration/Actions/PingWebPushSubscriptionActionTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Actions;

use AndyDefer\LaravelNotification\Actions\PingWebPushSubscriptionAction;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Http\Requests\PingWebPushSubscriptionRequest;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

final class PingWebPushSubscriptionActionTest extends TestCase
{
    private string $endpoint;

    private string $p256dh;

    private string $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->endpoint = (string) $this->getEnv('WEBPUSH_ENDPOINT');
        $this->p256dh = (string) $this->getEnv('WEBPUSH_P256DH');
        $this->auth = (string) $this->getEnv('WEBPUSH_AUTH');

        $this->app['config']->set('notification.channels.webpush', [
            'enabled' => true,
            'subject' => 'mailto:contact@afya-medical.com',
            'public_key' => (string) $this->getEnv('WEBPUSH_PUBLIC_KEY'),
            'private_key' => (string) $this->getEnv('WEBPUSH_PRIVATE_KEY'),
            'ttl' => 3600,
            'urgency' => 'normal',
            'topic' => 'notification',
        ]);

        $this->app->forgetInstance(NotificationConfig::class);
        $this->app->singleton(
            NotificationConfig::class,
            fn ($app) => new NotificationConfig($app['config']),
        );

        Route::post('/webpush/ping', action_route(
            PingWebPushSubscriptionRequest::class,
            PingWebPushSubscriptionAction::class,
        ))->middleware('nemesis.token');
    }

    private function createTokenFor(Model $model): string
    {
        $service = $this->app->make(NemesisInterface::class);

        $record = NemesisTokenRecord::from([
            'name' => 'WebPush Ping Test Token',
            'source' => 'api',
        ]);

        [, $plainToken] = $service->createWithPlainToken($record, $model);

        return $plainToken;
    }

    private function createSubscriptionFor(TestUser $user): WebPushSubscription
    {
        return WebPushSubscription::create([
            'endpoint' => $this->endpoint,
            'p256dh' => $this->p256dh,
            'auth' => $this->auth,
            'browser' => 'Firefox',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]);
    }

    // ============================================================================
    // Auth errors
    // ============================================================================

    public function test_returns_missing_token_when_no_bearer_is_provided(): void
    {
        $response = $this->postJson('/webpush/ping', [
            'subscription_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'MISSING_TOKEN',
            'message' => 'Token not provided',
        ]);
    }

    public function test_returns_invalid_token_when_bearer_is_garbage(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer garbage')
            ->postJson('/webpush/ping', [
                'subscription_id' => '00000000-0000-0000-0000-000000000000',
            ]);

        $response->assertStatus(401);
        $response->assertJson(['errorCode' => 'INVALID_TOKEN']);
    }

    // ============================================================================
    // Validation
    // ============================================================================

    public function test_returns_validation_error_when_subscription_id_is_missing(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/webpush/ping', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subscription_id']);
    }

    public function test_returns_validation_error_when_subscription_id_is_not_a_string(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/webpush/ping', [
                'subscription_id' => 12345,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subscription_id']);
    }

    // ============================================================================
    // Subscription resolution
    // ============================================================================

    public function test_returns_device_not_found_when_subscription_does_not_exist(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/webpush/ping', [
                'subscription_id' => '99999999-9999-9999-9999-999999999999',
            ]);

        $response->assertStatus(404);
        $response->assertJson(['errorCode' => 'DEVICE_NOT_FOUND']);
    }

    public function test_returns_notifiable_mismatch_when_subscription_belongs_to_another_user(): void
    {
        $owner = TestUser::create(['name' => 'Owner', 'email' => 'owner@example.com']);
        $intruder = TestUser::create(['name' => 'Intruder', 'email' => 'intruder@example.com']);

        $subscription = $this->createSubscriptionFor($owner);
        $token = $this->createTokenFor($intruder);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/webpush/ping', [
                'subscription_id' => $subscription->id,
            ]);

        $response->assertStatus(403);
        $response->assertJson(['errorCode' => 'NOTIFIABLE_MISMATCH']);
    }

    // ============================================================================
    // Success path
    // ============================================================================

    public function test_returns_ping_status_when_subscription_belongs_to_the_authenticated_user(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $subscription = $this->createSubscriptionFor($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/webpush/ping', [
                'subscription_id' => $subscription->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['deviceId', 'status']);

        $this->assertSame($subscription->id, $response->json('deviceId'));

        $status = $response->json('status');

        $this->assertContains($status, [
            PingStatus::PONG->value,
            PingStatus::INVALID->value,
        ]);
    }

    public function test_prunes_subscription_when_webpush_reports_invalid(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $subscription = $this->createSubscriptionFor($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/webpush/ping', [
                'subscription_id' => $subscription->id,
            ]);

        $response->assertStatus(200);

        if ($response->json('status') === PingStatus::INVALID->value) {
            $this->assertDatabaseMissing('web_push_subscriptions', ['id' => $subscription->id]);
        } else {
            $this->assertDatabaseHas('web_push_subscriptions', ['id' => $subscription->id]);
        }
    }
}
