<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Actions;

use AndyDefer\LaravelNotification\Actions\RegisterFcmDeviceAction;
use AndyDefer\LaravelNotification\Http\Requests\RegisterFcmDeviceRequest;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

final class RegisterFcmDeviceActionTest extends TestCase
{
    private const DEVICE_ID = '550e8400-e29b-41d4-a716-446655440000';

    private const TOKEN = 'eQhyKer9t_jperz7AYDDVB:APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s';

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/fcm/devices', action_route(
            RegisterFcmDeviceRequest::class,
            RegisterFcmDeviceAction::class,
        ))->middleware('nemesis.token');
    }

    private function createTokenFor(Model $model): string
    {
        $service = $this->app->make(NemesisInterface::class);

        $record = NemesisTokenRecord::from([
            'name' => 'FCM Test Token',
            'source' => 'api',
        ]);

        [, $plainToken] = $service->createWithPlainToken($record, $model);

        return $plainToken;
    }

    public function test_returns_missing_token_when_no_bearer_is_provided(): void
    {
        $response = $this->postJson('/fcm/devices', [
            'device_id' => self::DEVICE_ID,
            'token' => self::TOKEN,
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
            ->postJson('/fcm/devices', [
                'device_id' => self::DEVICE_ID,
                'token' => self::TOKEN,
            ]);

        $response->assertStatus(401);
        $response->assertJson(['errorCode' => 'INVALID_TOKEN']);
    }

    public function test_returns_error_when_device_id_is_invalid(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices', [
                'device_id' => 'not-a-uuid',
                'token' => self::TOKEN,
            ]);

        $response->assertStatus(422);
    }

    public function test_returns_error_when_token_is_too_short(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices', [
                'device_id' => self::DEVICE_ID,
                'token' => 'short',
            ]);

        $response->assertStatus(422);
    }

    public function test_returns_error_when_device_id_is_missing(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices', [
                'token' => self::TOKEN,
            ]);

        $response->assertStatus(422);
    }

    public function test_returns_error_when_token_is_missing(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices', [
                'device_id' => self::DEVICE_ID,
            ]);

        $response->assertStatus(422);
    }

    public function test_registers_device_successfully(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices', [
                'device_id' => self::DEVICE_ID,
                'token' => self::TOKEN,
                'platform' => 'web',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id',
            'deviceId',
            'token',
            'platform',
            'lastSeenAt',
        ]);

        $response->assertJson([
            'deviceId' => self::DEVICE_ID,
            'token' => self::TOKEN,
            'platform' => 'web',
        ]);

        $this->assertDatabaseHas('fcm_devices', [
            'device_id' => self::DEVICE_ID,
            'token' => self::TOKEN,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]);
    }

    public function test_refreshes_token_on_existing_device(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices', [
                'device_id' => self::DEVICE_ID,
                'token' => self::TOKEN,
            ])->assertStatus(200);

        $newToken = 'NEW_APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s';

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices', [
                'device_id' => self::DEVICE_ID,
                'token' => $newToken,
            ])
            ->assertStatus(200)
            ->assertJson(['token' => $newToken]);

        $this->assertSame(1, FcmDevice::count());
        $this->assertSame($newToken, FcmDevice::first()->token);
    }

    public function test_accepts_missing_platform(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices', [
                'device_id' => self::DEVICE_ID,
                'token' => self::TOKEN,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['platform' => null]);
    }
}
