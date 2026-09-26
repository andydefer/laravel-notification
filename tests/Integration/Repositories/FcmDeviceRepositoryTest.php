<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Integration\Repositories;

use AndyDefer\LaravelNotification\Contracts\Repositories\FcmDeviceRepositoryInterface;
use AndyDefer\LaravelNotification\Enums\FcmPlatform;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Records\FcmDeviceFilterRecord;
use AndyDefer\LaravelNotification\Records\FcmDeviceRecord;
use AndyDefer\LaravelNotification\Repositories\FcmDeviceRepository;
use AndyDefer\LaravelNotification\Tests\Fixtures\Models\TestUser;
use AndyDefer\LaravelNotification\Tests\TestCase;
use AndyDefer\Repository\Records\FindByRecord;
use AndyDefer\Repository\ValueObjects\SortColumns;

final class FcmDeviceRepositoryTest extends TestCase
{
    private const DEVICE_ID = '550e8400-e29b-41d4-a716-446655440000';

    private const TOKEN = 'eQhyKer9t_jperz7AYDDVB:APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s';

    private FcmDeviceRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(FcmDeviceRepositoryInterface::class);
    }

    public function test_resolves_interface_from_container(): void
    {
        $this->assertInstanceOf(FcmDeviceRepository::class, $this->repository);
    }

    public function test_upsert_for_creates_new_device(): void
    {
        $user = TestUser::create(['name' => 'John']);

        $device = $this->repository->upsertFor(new FcmDeviceRecord(
            device_id: self::DEVICE_ID,
            token: self::TOKEN,
            platform: FcmPlatform::WEB,
            user_agent: 'Mozilla/5.0',
            notifiable_type: $user->getMorphClass(),
            notifiable_id: (string) $user->getKey(),
        ));

        $this->assertInstanceOf(FcmDevice::class, $device);
        $this->assertSame(self::DEVICE_ID, $device->device_id);
        $this->assertSame(self::TOKEN, $device->token);
        $this->assertSame('web', $device->platform);
        $this->assertNotNull($device->last_seen_at);

        $this->assertDatabaseHas('fcm_devices', [
            'device_id' => self::DEVICE_ID,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]);
    }

    public function test_upsert_for_updates_existing_device_token(): void
    {
        $user = TestUser::create(['name' => 'John']);

        $this->repository->upsertFor(new FcmDeviceRecord(
            device_id: self::DEVICE_ID,
            token: self::TOKEN,
            notifiable_type: $user->getMorphClass(),
            notifiable_id: (string) $user->getKey(),
        ));

        $newToken = 'NEW_TOKEN_APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s';

        $updated = $this->repository->upsertFor(new FcmDeviceRecord(
            device_id: self::DEVICE_ID,
            token: $newToken,
            notifiable_type: $user->getMorphClass(),
            notifiable_id: (string) $user->getKey(),
        ));

        $this->assertSame($newToken, $updated->token);
        $this->assertSame(1, FcmDevice::count());
    }

    public function test_upsert_for_distinguishes_devices_of_same_owner(): void
    {
        $user = TestUser::create(['name' => 'John']);

        $this->repository->upsertFor(new FcmDeviceRecord(
            device_id: self::DEVICE_ID,
            token: self::TOKEN,
            notifiable_type: $user->getMorphClass(),
            notifiable_id: (string) $user->getKey(),
        ));

        $this->repository->upsertFor(new FcmDeviceRecord(
            device_id: '660e8400-e29b-41d4-a716-446655440001',
            token: 'OTHER_TOKEN_APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s',
            notifiable_type: $user->getMorphClass(),
            notifiable_id: (string) $user->getKey(),
        ));

        $this->assertSame(2, FcmDevice::count());
    }

    public function test_find_by_filters_by_notifiable_id(): void
    {
        $userA = TestUser::create(['name' => 'A']);
        $userB = TestUser::create(['name' => 'B']);

        $this->repository->upsertFor(new FcmDeviceRecord(
            device_id: self::DEVICE_ID,
            token: self::TOKEN,
            notifiable_type: $userA->getMorphClass(),
            notifiable_id: (string) $userA->getKey(),
        ));

        $this->repository->upsertFor(new FcmDeviceRecord(
            device_id: '770e8400-e29b-41d4-a716-446655440002',
            token: 'TOKEN_B_APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s',
            notifiable_type: $userB->getMorphClass(),
            notifiable_id: (string) $userB->getKey(),
        ));

        $result = $this->repository->findBy(new FindByRecord(
            filters: new FcmDeviceFilterRecord(
                notifiable_id: (string) $userA->getKey(),
            ),
            sortBy: new SortColumns('created_at:desc'),
        ));

        $this->assertCount(1, $result);
        $this->assertSame((string) $userA->getKey(), $result->first()->notifiable_id);
    }

    public function test_count_with_filters(): void
    {
        $user = TestUser::create(['name' => 'John']);

        $this->repository->upsertFor(new FcmDeviceRecord(
            device_id: self::DEVICE_ID,
            token: self::TOKEN,
            platform: FcmPlatform::WEB,
            notifiable_type: $user->getMorphClass(),
            notifiable_id: (string) $user->getKey(),
        ));

        $this->repository->upsertFor(new FcmDeviceRecord(
            device_id: '880e8400-e29b-41d4-a716-446655440003',
            token: 'TOKEN_IOS_APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s',
            platform: FcmPlatform::IOS,
            notifiable_type: $user->getMorphClass(),
            notifiable_id: (string) $user->getKey(),
        ));

        $count = $this->repository->count(new FcmDeviceFilterRecord(
            platform: FcmPlatform::IOS,
        ));

        $this->assertSame(1, $count);
    }
}
