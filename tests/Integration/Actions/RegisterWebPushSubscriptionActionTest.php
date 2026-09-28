<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Actions;

use AndyDefer\LaravelNotification\Actions\RegisterWebPushSubscriptionAction;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Http\Requests\RegisterWebPushSubscriptionRequest;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

final class RegisterWebPushSubscriptionActionTest extends TestCase
{
    private const FIREFOX_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64; rv:124.0) Gecko/20100101 Firefox/124.0';

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
            fn ($app) => new NotificationConfig($app['config'])
        );

        Route::post('/webpush/register', action_route(
            RegisterWebPushSubscriptionRequest::class,
            RegisterWebPushSubscriptionAction::class,
        ))->middleware('nemesis.token');
    }

    private function createTokenFor(Model $model): string
    {
        $service = $this->app->make(NemesisInterface::class);

        $record = NemesisTokenRecord::from([
            'name' => 'WebPush Test Token',
            'source' => 'api',
        ]);

        [, $plainToken] = $service->createWithPlainToken($record, $model);

        return $plainToken;
    }

    // ============================================================================
    // Auth errors
    // ============================================================================

    public function test_returns_missing_token_when_no_bearer_is_provided(): void
    {
        $response = $this->postJson('/webpush/register', [
            'endpoint' => $this->endpoint,
            'p256dh' => $this->p256dh,
            'auth' => $this->auth,
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
            ->postJson('/webpush/register', [
                'endpoint' => $this->endpoint,
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
        ]);
    }

    // ============================================================================
    // Validation
    // ============================================================================

    public function test_returns_validation_error_when_endpoint_is_missing(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/webpush/register', [
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint']);
    }

    public function test_returns_validation_error_when_endpoint_is_not_a_url(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/webpush/register', [
                'endpoint' => 'not-a-url',
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endpoint']);
    }

    public function test_returns_validation_error_when_p256dh_is_missing(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/webpush/register', [
                'endpoint' => $this->endpoint,
                'auth' => $this->auth,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['p256dh']);
    }

    public function test_returns_validation_error_when_auth_is_missing(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/webpush/register', [
                'endpoint' => $this->endpoint,
                'p256dh' => $this->p256dh,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['auth']);
    }

    // ============================================================================
    // Successful registration
    // ============================================================================

    public function test_registers_subscription_and_returns_data(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('User-Agent', self::FIREFOX_USER_AGENT)
            ->postJson('/webpush/register', [
                'endpoint' => $this->endpoint,
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'endpoint', 'browser', 'lastSeenAt']);

        $json = $response->json();
        $this->assertSame($this->endpoint, $json['endpoint']);
        $this->assertSame('Firefox', $json['browser']);

        $this->assertDatabaseHas('web_push_subscriptions', [
            'endpoint' => $this->endpoint,
            'p256dh' => $this->p256dh,
            'auth' => $this->auth,
            'browser' => 'Firefox',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]);
    }

    public function test_upserts_when_endpoint_already_exists(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        // Premier enregistrement avec Firefox
        $first = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('User-Agent', self::FIREFOX_USER_AGENT)
            ->postJson('/webpush/register', [
                'endpoint' => $this->endpoint,
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ]);

        $first->assertStatus(200);
        $firstId = $first->json('id');

        // Deuxième enregistrement avec un user-agent Chrome
        $chromeUserAgent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

        $second = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('User-Agent', $chromeUserAgent)
            ->postJson('/webpush/register', [
                'endpoint' => $this->endpoint,
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ]);

        $second->assertStatus(200);

        $this->assertSame($firstId, $second->json('id'));
        $this->assertSame('Chrome', $second->json('browser'));

        $this->assertDatabaseCount('web_push_subscriptions', 1);
    }

    public function test_returns_unknown_browser_when_user_agent_is_unrecognized(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('User-Agent', 'curl/8.5.0')
            ->postJson('/webpush/register', [
                'endpoint' => $this->endpoint,
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ]);

        $response->assertStatus(200);
        $this->assertSame('unknown', $response->json('browser'));
    }

    public function test_different_users_can_register_different_endpoints(): void
    {
        $userA = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        $userB = TestUser::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $tokenA = $this->createTokenFor($userA);
        $tokenB = $this->createTokenFor($userB);

        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->withHeader('User-Agent', self::FIREFOX_USER_AGENT)
            ->postJson('/webpush/register', [
                'endpoint' => $this->endpoint,
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ])->assertStatus(200);

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->withHeader('User-Agent', self::FIREFOX_USER_AGENT)
            ->postJson('/webpush/register', [
                'endpoint' => $this->endpoint.'-b',
                'p256dh' => $this->p256dh,
                'auth' => $this->auth,
            ])->assertStatus(200);

        $this->assertDatabaseCount('web_push_subscriptions', 2);
    }
}
