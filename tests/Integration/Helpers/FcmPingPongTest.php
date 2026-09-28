<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Helpers;

use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Helpers\FcmPingPong;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;

final class FcmPingPongTest extends TestCase
{
    private FcmPingPong $pingPong;

    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pingPong = app(FcmPingPong::class);

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

    private function makeDevice(string $token): FcmDevice
    {
        return FcmDevice::create([
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'token' => $token,
            'platform' => 'web',
            'notifiable_type' => $this->user->getMorphClass(),
            'notifiable_id' => (string) $this->user->getKey(),
            'last_seen_at' => now()->subDay(),
        ]);
    }

    public function test_resolves_helper_from_container(): void
    {
        $this->assertInstanceOf(FcmPingPong::class, $this->pingPong);
    }

    public function test_ping_returns_pong_when_fcm_accepts(): void
    {
        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        $device = $this->makeDevice($token);
        $before = $device->last_seen_at;

        $status = $this->pingPong->ping($device);

        $this->assertSame(PingStatus::PONG, $status);

        $device->refresh();
        $this->assertTrue($device->last_seen_at->greaterThan($before));
    }

    public function test_ping_returns_invalid_when_token_is_unregistered(): void
    {
        // Token syntaxiquement valide mais désenregistré côté FCM.
        // FCM retourne UNREGISTERED → PingStatus::INVALID.
        $device = $this->makeDevice(str_repeat('a', 200));

        $status = $this->pingPong->ping($device);

        $this->assertSame(PingStatus::INVALID, $status);
    }

    public function test_ping_returns_invalid_when_driver_config_is_missing(): void
    {
        // Force une erreur de config → envoi échoue → INVALID.
        $this->app['config']->set('notification.channels.firebase.enabled', false);

        $device = $this->makeDevice(str_repeat('a', 200));

        $status = $this->pingPong->ping($device);

        $this->assertSame(PingStatus::INVALID, $status);
    }

    public function test_is_alive_returns_true_when_pong(): void
    {
        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        $device = $this->makeDevice($token);

        $this->assertTrue($this->pingPong->isAlive($device));
    }

    public function test_is_alive_returns_false_when_invalid(): void
    {
        $device = $this->makeDevice(str_repeat('a', 200));

        $this->assertFalse($this->pingPong->isAlive($device));
    }

    public function test_ping_or_prune_deletes_device_when_invalid(): void
    {
        $device = $this->makeDevice(str_repeat('a', 200));
        $deviceId = $device->id;

        $status = $this->pingPong->pingOrPrune($device);

        $this->assertSame(PingStatus::INVALID, $status);
        $this->assertDatabaseMissing('fcm_devices', ['id' => $deviceId]);
    }

    public function test_ping_or_prune_keeps_device_when_pong(): void
    {
        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        $device = $this->makeDevice($token);
        $deviceId = $device->id;

        $status = $this->pingPong->pingOrPrune($device);

        $this->assertSame(PingStatus::PONG, $status);
        $this->assertDatabaseHas('fcm_devices', ['id' => $deviceId]);
    }

    public function test_ping_or_prune_deletes_device_when_config_is_missing(): void
    {
        $this->app['config']->set('notification.channels.firebase.enabled', false);

        $device = $this->makeDevice(str_repeat('a', 200));
        $deviceId = $device->id;

        $status = $this->pingPong->pingOrPrune($device);

        $this->assertSame(PingStatus::INVALID, $status);
        $this->assertDatabaseMissing('fcm_devices', ['id' => $deviceId]);
    }

    public function test_device_ping_method_delegates_to_helper(): void
    {
        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        $device = $this->makeDevice($token);

        $this->assertSame(PingStatus::PONG, $device->ping());
    }

    public function test_device_is_alive_method_delegates_to_helper(): void
    {
        $token = (string) $this->getEnv('FIREBASE_DEVICE_TOKEN');

        $device = $this->makeDevice($token);

        $this->assertTrue($device->isAlive());
    }

    public function test_device_ping_or_prune_method_delegates_to_helper(): void
    {
        $device = $this->makeDevice(str_repeat('a', 200));
        $deviceId = $device->id;

        $status = $device->pingOrPrune();

        $this->assertSame(PingStatus::INVALID, $status);
        $this->assertDatabaseMissing('fcm_devices', ['id' => $deviceId]);
    }

    public function test_ping_updates_last_seen_at_only_on_pong(): void
    {
        $invalidDevice = $this->makeDevice(str_repeat('a', 200));
        $before = $invalidDevice->last_seen_at;

        $this->pingPong->ping($invalidDevice);

        $invalidDevice->refresh();
        $this->assertEquals($before->getTimestamp(), $invalidDevice->last_seen_at->getTimestamp());
    }
}
