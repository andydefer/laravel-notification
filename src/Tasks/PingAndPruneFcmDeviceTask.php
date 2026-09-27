<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tasks;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Helpers\FcmPingPong;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\Task\Abstract\AbstractUniqueTask;
use AndyDefer\Task\ValueObjects\DescriptionVO;

final class PingAndPruneFcmDeviceTask extends AbstractUniqueTask
{
    protected function before(StrictDataObject $payload): void
    {
        if (! $payload->has('device_id')) {
            throw new \InvalidArgumentException('device_id is required.');
        }

        if ((string) $payload->device_id === '') {
            throw new \InvalidArgumentException('device_id cannot be empty.');
        }
    }

    protected function process(): void
    {
        $deviceId = (string) $this->context->getPayload()->device_id;
        $device = FcmDevice::find($deviceId);

        if ($device === null) {
            throw new \RuntimeException(sprintf('FCM device not found: %s', $deviceId));
        }

        /** @var FcmPingPong $pingPong */
        $pingPong = $this->context->getLaravelApp()->make(FcmPingPong::class);

        $this->info(new DescriptionVO(sprintf(
            'Pinging FCM device %s (token ...%s)',
            $device->id,
            substr((string) $device->token, -12),
        )));

        $status = $pingPong->pingOrPrune($device);

        $this->info(new DescriptionVO(sprintf(
            'Device %s -> %s',
            $device->id,
            $status->value,
        )));

        if ($status !== PingStatus::PONG) {
            $this->info(new DescriptionVO(sprintf(
                'Device %s is not reachable (%s).',
                $device->id,
                $status->value,
            )));
        }
    }

    protected function after(bool $success, ?DescriptionVO $error = null): void
    {
        if ($success) {
            return;
        }

        $this->error(new DescriptionVO(sprintf(
            'Ping/prune failed: %s',
            $error?->getValue() ?? 'unknown error',
        )));
    }
}
