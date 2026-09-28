<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Helpers;

use AndyDefer\LaravelNotification\Contracts\PingPongInterface;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Resolves the appropriate {@see PingPongInterface} implementation
 * for a given notifiable model.
 *
 * Centralizes the mapping between a model class and its ping/pong
 * helper, so that model classes themselves stay free from channel
 * specific logic.
 */
final class PingPongAdapter
{
    /**
     * @var array<class-string<Model>, class-string<PingPongInterface>>
     */
    private const HELPERS = [
        FcmDevice::class => FcmPingPong::class,
        WebPushSubscription::class => WebPushPingPong::class,
    ];

    /**
     * Resolve the ping/pong helper for the given model.
     *
     * @param  Model  $model  The model that requires a ping/pong helper
     *
     * @throws RuntimeException When no helper is registered for the model class
     */
    public function for(Model $model): PingPongInterface
    {
        $class = $model::class;

        if (! isset(self::HELPERS[$class])) {
            throw new RuntimeException(sprintf(
                'No ping/pong helper registered for model [%s].',
                $class,
            ));
        }

        return app(self::HELPERS[$class]);
    }
}
