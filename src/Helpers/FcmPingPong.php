<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Helpers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Contracts\PingableInterface;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Ping/pong helper for FCM devices.
 *
 * Sends a lightweight notification to a device's FCM token to verify
 * that the token is still registered and reachable. Distinguishes
 * between a definitively invalid token (UNREGISTERED, SENDER_ID_MISMATCH)
 * and an unreachable one (network, FCM unavailable).
 */
final class FcmPingPong
{
    public const PING_TYPE = 'ping';

    public const PING_SUBJECT = 'ping';

    public const PING_BODY = 'ping';

    /**
     * FCM error codes that definitively invalidate a token.
     *
     * @var array<int, string>
     */
    private const INVALID_ERROR_CODES = [
        'UNREGISTERED',
        'INVALID_ARGUMENT',
        'SENDER_ID_MISMATCH',
    ];

    public function __construct(
        private readonly NotificationServiceInterface $service,
    ) {}

    /**
     * Send a ping to the device.
     *
     * @param  Model&NotifiableInterface&PingableInterface  $device
     */
    public function ping(Model $device): PingStatus
    {
        if (! $device instanceof NotifiableInterface) {
            throw new RuntimeException('Device must implement NotifiableInterface.');
        }

        $message = new NotificationMessageVO(
            body: new MessageBodyVO(self::PING_BODY),
            subject: new MessageSubjectVO(self::PING_SUBJECT),
            type: self::PING_TYPE,
            data: new StrictDataObject([
                'device_id' => (string) ($device->getAttribute('device_id') ?? ''),
                'token_id' => (string) $device->getKey(),
            ]),
        );

        $channels = new FqcnChannelCollection;
        $channels->add(new FqcnChannelVO(FirebaseCloudMessagingChannel::class));

        $record = SendNowRecord::from([
            'channels' => $channels,
            'limit_per_channel' => 1,
        ]);

        $result = $this->service->sendNow($device, $message, $record);

        if ($result->allSuccess()) {
            $device->setAttribute('last_seen_at', now());
            $device->save();

            return PingStatus::PONG;
        }

        $failure = $result->first();
        $errorMessage = $failure?->error_message?->getValue() ?? '';

        if ($this->isDefinitelyInvalid($errorMessage)) {
            return PingStatus::INVALID;
        }

        return PingStatus::UNREACHABLE;
    }

    /**
     * Return true when the device answers with a pong.
     *
     * @param  Model&NotifiableInterface&PingableInterface  $device
     */
    public function isAlive(Model $device): bool
    {
        return $this->ping($device)->isPong();
    }

    /**
     * Ping the device and delete it when it is definitively invalid.
     *
     * @param  Model&NotifiableInterface&PingableInterface  $device
     */
    public function pingOrPrune(Model $device): PingStatus
    {
        $status = $this->ping($device);

        if ($status->isInvalid()) {
            $device->delete();
        }

        return $status;
    }

    private function isDefinitelyInvalid(string $errorMessage): bool
    {
        if ($errorMessage === '') {
            return false;
        }

        foreach (self::INVALID_ERROR_CODES as $code) {
            if (str_contains($errorMessage, $code)) {
                return true;
            }
        }

        return false;
    }
}
