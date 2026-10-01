<?php

// tests/Integration/Actions/PingFcmDeviceActionTest.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Actions;

use AndyDefer\LaravelNotification\Actions\PingFcmDeviceAction;
use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Http\Requests\PingFcmDeviceRequest;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

final class PingFcmDeviceActionTest extends TestCase
{
    private const DEVICE_ID = '550e8400-e29b-41d4-a716-446655440000';

    private const TOKEN = 'eQhyKer9t_jperz7AYDDVB:APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s';

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/fcm/devices/ping', action_route(
            PingFcmDeviceRequest::class,
            PingFcmDeviceAction::class,
        ))->middleware('nemesis.token');
    }

    private function createTokenFor(Model $model): string
    {
        $service = $this->app->make(NemesisInterface::class);

        $record = NemesisTokenRecord::from([
            'name' => 'FCM Ping Test Token',
            'source' => 'api',
        ]);

        [, $plainToken] = $service->createWithPlainToken($record, $model);

        return $plainToken;
    }

    private function createDeviceFor(TestUser $user): FcmDevice
    {
        return FcmDevice::create([
            'device_id' => self::DEVICE_ID,
            'token' => self::TOKEN,
            'platform' => 'web',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]);
    }

    // ============================================================================
    // Auth errors
    // ============================================================================

    public function test_returns_missing_token_when_no_bearer_is_provided(): void
    {
        $response = $this->postJson('/fcm/devices/ping', [
            'device_id' => self::DEVICE_ID,
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
            ->postJson('/fcm/devices/ping', [
                'device_id' => self::DEVICE_ID,
            ]);

        $response->assertStatus(401);
        $response->assertJson(['errorCode' => 'INVALID_TOKEN']);
    }

    // ============================================================================
    // Validation
    // ============================================================================

    public function test_returns_validation_error_when_device_id_is_missing(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices/ping', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['device_id']);
    }

    public function test_returns_validation_error_when_device_id_is_not_a_string(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices/ping', [
                'device_id' => 12345,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['device_id']);
    }

    // ============================================================================
    // Device resolution
    // ============================================================================

    public function test_returns_device_not_found_when_device_does_not_exist(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices/ping', [
                'device_id' => '99999999-9999-9999-9999-999999999999',
            ]);

        $response->assertStatus(404);
        $response->assertJson(['errorCode' => 'DEVICE_NOT_FOUND']);
    }

    public function test_returns_notifiable_mismatch_when_device_belongs_to_another_user(): void
    {
        $owner = TestUser::create(['name' => 'Owner', 'email' => 'owner@example.com']);
        $intruder = TestUser::create(['name' => 'Intruder', 'email' => 'intruder@example.com']);

        $device = $this->createDeviceFor($owner);
        $token = $this->createTokenFor($intruder);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices/ping', [
                'device_id' => $device->id,
            ]);

        $response->assertStatus(403);
        $response->assertJson(['errorCode' => 'NOTIFIABLE_MISMATCH']);
    }

    // ============================================================================
    // Success path
    // ============================================================================

    public function test_returns_ping_status_when_device_belongs_to_the_authenticated_user(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $device = $this->createDeviceFor($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices/ping', [
                'device_id' => $device->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['deviceId', 'status']);

        $this->assertSame($device->id, $response->json('deviceId'));

        $status = $response->json('status');

        $this->assertContains($status, [
            PingStatus::PONG->value,
            PingStatus::INVALID->value,
        ]);
    }

    public function test_prunes_device_when_fcm_reports_invalid(): void
    {
        $user = TestUser::create(['name' => 'John']);
        $device = $this->createDeviceFor($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/fcm/devices/ping', [
                'device_id' => $device->id,
            ]);

        $response->assertStatus(200);

        if ($response->json('status') === PingStatus::INVALID->value) {
            $this->assertDatabaseMissing('fcm_devices', ['id' => $device->id]);
        } else {
            $this->assertDatabaseHas('fcm_devices', ['id' => $device->id]);
        }
    }
}
