<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Contracts\Repositories\FcmDeviceRepositoryInterface;
use AndyDefer\LaravelNotification\Datas\FcmDeviceData;
use AndyDefer\LaravelNotification\Records\FcmDeviceRecord;
use AndyDefer\LaravelNotification\Records\RegisterFcmDeviceRecord;
use AndyDefer\Nemesis\Enums\ErrorCode;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use Illuminate\Database\Eloquent\Model;

final class RegisterFcmDeviceAction extends AbstractAction
{
    public function __construct(
        private readonly NemesisHelper $helper,
        private readonly FcmDeviceRepositoryInterface $devices,
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var RegisterFcmDeviceRecord $request */
        if (! $this->helper->isAuthenticated()) {
            return ErrorCode::MISSING_TOKEN->toJsonResponseFactory();
        }

        $user = $this->helper->getCurrentAuthenticatable();

        if (! $user instanceof Model) {
            return ErrorCode::AUTHENTICATABLE_NOT_FOUND->toJsonResponseFactory();
        }

        $device = $this->devices->upsertFor(FcmDeviceRecord::from([
            'device_id' => $request->device_id,
            'token' => $request->token,
            'platform' => $request->platform,
            'user_agent' => $request->user_agent,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->getKey(),
        ]));

        return ResponseFactory::json(
            FcmDeviceData::from([
                'id' => $device->id,
                'deviceId' => $device->device_id,
                'token' => $device->token,
                'platform' => $device->platform,
                'lastSeenAt' => $device->last_seen_at?->toIso8601String(),
            ]),
        );
    }
}
