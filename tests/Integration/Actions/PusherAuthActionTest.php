<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Actions;

use AndyDefer\LaravelNotification\Actions\PusherAuthAction;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Http\Requests\PusherAuthRequest;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

final class PusherAuthActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('notification.channels.pusher', [
            'enabled' => true,
            'app_id' => '2197533',
            'key' => 'f1bc1733c3f2f2e30cdd',
            'secret' => '2613a0ff0eddf57f8d6f',
            'cluster' => 'ap2',
            'use_tls' => true,
            'timeout' => 30,
            'default_channel' => 'notifications',
        ]);

        $this->app->forgetInstance(NotificationConfig::class);
        $this->app->singleton(
            NotificationConfig::class,
            fn ($app) => new NotificationConfig($app['config'])
        );

        Route::post('/pusher/auth', action_route(
            PusherAuthRequest::class,
            PusherAuthAction::class,
        ))->middleware('nemesis.token');
    }

    private function createTokenFor(Model $model): string
    {
        $service = $this->app->make(NemesisInterface::class);

        $record = NemesisTokenRecord::from([
            'name' => 'Pusher Test Token',
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
        $response = $this->postJson('/pusher/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-user-App.Models.User-1',
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
            ->postJson('/pusher/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-user-App.Models.User-1',
            ]);

        $response->assertStatus(401);
        $response->assertJson([
            'errorCode' => 'INVALID_TOKEN',
        ]);
    }

    // ============================================================================
    // Channel authorization
    // ============================================================================

    public function test_returns_forbidden_when_channel_does_not_match_user(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/pusher/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-user-App.Models.OtherModel-999',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['errorCode' => 'ORIGIN_NOT_ALLOWED']);
    }

    public function test_returns_forbidden_when_channel_name_is_public(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/pusher/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'public-channel',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['errorCode' => 'ORIGIN_NOT_ALLOWED']);
    }

    public function test_returns_forbidden_when_user_id_does_not_match(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/pusher/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-user-'.$this->sanitize($user->getMorphClass()).'-99999',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['errorCode' => 'ORIGIN_NOT_ALLOWED']);
    }

    // ============================================================================
    // Successful authorization
    // ============================================================================

    public function test_authorizes_private_user_channel(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $channel = sprintf(
            'private-user-%s-%s',
            $this->sanitize($user->getMorphClass()),
            $user->getKey(),
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/pusher/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => $channel,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['auth', 'channelData']);

        $json = $response->json();
        $this->assertIsString($json['auth']);
        $this->assertNotEmpty($json['auth']);
        $this->assertStringStartsWith($this->pusherKey().':', $json['auth']);
    }

    public function test_authorizes_device_subchannel(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $channel = sprintf(
            'private-user-%s-%s-device-abc123',
            $this->sanitize($user->getMorphClass()),
            $user->getKey(),
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/pusher/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => $channel,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['auth', 'channelData']);
    }

    public function test_returns_error_when_socket_id_is_missing(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/pusher/auth', [
                'channel_name' => 'private-user-App.Models.User-1',
            ]);

        $response->assertStatus(422);
    }

    public function test_returns_error_when_channel_name_is_missing(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/pusher/auth', [
                'socket_id' => '1234.5678',
            ]);

        $response->assertStatus(422);
    }

    // ============================================================================
    // Helpers
    // ============================================================================

    private function pusherKey(): string
    {
        return (string) $this->app['config']->get('notification.channels.pusher.key');
    }

    private function sanitize(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-=@,.;]/', '_', $value) ?? '';
    }
}
