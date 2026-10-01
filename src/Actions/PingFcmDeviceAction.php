<?php

// src/Actions/PingFcmDeviceAction.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Datas\PingData;
use AndyDefer\LaravelNotification\Enums\ErrorCode;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Records\PingFcmDeviceRecord;
use AndyDefer\Nemesis\Enums\ErrorCode as NemesisErrorCode;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use Illuminate\Database\Eloquent\Model;
use Throwable;

final class PingFcmDeviceAction extends AbstractAction
{
    public function __construct(
        private readonly NemesisHelper $helper,
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var PingFcmDeviceRecord $request */
        if (! $this->helper->isAuthenticated()) {
            return NemesisErrorCode::MISSING_TOKEN->toJsonResponseFactory();
        }

        $user = $this->helper->getCurrentAuthenticatable();

        if (! $user instanceof Model) {
            return NemesisErrorCode::AUTHENTICATABLE_NOT_FOUND->toJsonResponseFactory();
        }

        $device = FcmDevice::find($request->device_id);

        if (! $device instanceof FcmDevice) {
            return ErrorCode::DEVICE_NOT_FOUND->toJsonResponseFactory();
        }

        $belongsToUser = $device->notifiable_type === $user->getMorphClass()
            && (string) $device->notifiable_id === (string) $user->getKey();

        if (! $belongsToUser) {
            return ErrorCode::NOTIFIABLE_MISMATCH->toJsonResponseFactory();
        }

        try {
            $status = $device->pingOrPrune();
        } catch (Throwable $e) {
            return ErrorCode::PING_FAILED->toJsonResponseFactory(
                message: $e->getMessage(),
            );
        }

        return ResponseFactory::json(PingData::from([
            'device_id' => (string) $device->getKey(),
            'status' => $status,
        ]));
    }
}
