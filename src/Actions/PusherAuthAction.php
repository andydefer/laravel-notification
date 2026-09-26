<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Actions;

use AndyDefer\Actions\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Contracts\Configs\NotificationConfigInterface;
use AndyDefer\LaravelNotification\Datas\PusherAuthData;
use AndyDefer\LaravelNotification\Records\PusherAuthRecord;
use AndyDefer\Nemesis\Enums\ErrorCode;
use AndyDefer\Nemesis\Helpers\NemesisHelper;
use Illuminate\Database\Eloquent\Model;
use Pusher\Pusher;
use Pusher\PusherException;

final class PusherAuthAction extends AbstractAction
{
    public function __construct(
        private readonly NemesisHelper $helper,
        private readonly NotificationConfigInterface $notificationConfig,
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var PusherAuthRecord $request */
        if (! $this->helper->isAuthenticated()) {
            return ErrorCode::MISSING_TOKEN->toJsonResponseFactory();
        }

        $user = $this->helper->getCurrentAuthenticatable();

        if (! $user instanceof Model) {
            return ErrorCode::AUTHENTICATABLE_NOT_FOUND->toJsonResponseFactory();
        }

        if (! $this->isChannelAllowedForUser($request->channel_name, $user)) {
            return ErrorCode::ORIGIN_NOT_ALLOWED->toJsonResponseFactory('Forbidden channel');
        }

        try {
            $auth = $this->authorizeChannel($request->channel_name, $request->socket_id);
        } catch (PusherException) {
            return ErrorCode::INVALID_TOKEN->toJsonResponseFactory('Pusher authentication failed');
        }

        return ResponseFactory::json($auth);
    }

    private function authorizeChannel(string $channelName, string $socketId): PusherAuthData
    {
        $config = $this->notificationConfig->getPusherConfig();

        $pusher = new Pusher(
            $config->key,
            $config->secret,
            $config->app_id,
            [
                'cluster' => $config->cluster,
                'useTLS' => $config->use_tls,
                'timeout' => $config->timeout,
            ],
        );

        $payload = json_decode(
            $pusher->authorizeChannel($channelName, $socketId),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        return PusherAuthData::from([
            'auth' => (string) ($payload['auth'] ?? ''),
            'channel_data' => isset($payload['channel_data'])
                ? (string) $payload['channel_data']
                : null,
        ]);
    }

    private function isChannelAllowedForUser(string $channelName, Model $user): bool
    {
        $morphType = $user->getMorphClass();
        $key = $user->getKey();

        if ($key === null || $morphType === '') {
            return false;
        }

        $prefix = sprintf(
            'private-user-%s-%s',
            $this->sanitizeChannelSegment($morphType),
            $this->sanitizeChannelSegment((string) $key),
        );

        if ($channelName === $prefix) {
            return true;
        }

        return str_starts_with($channelName, $prefix.'-device-');
    }

    private function sanitizeChannelSegment(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-=@,.;]/', '_', $value) ?? '';
    }
}
