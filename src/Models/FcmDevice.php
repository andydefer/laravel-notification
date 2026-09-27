<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Models;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Contracts\PingableInterface;
use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Helpers\FcmPingPong;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class FcmDevice extends Model implements NotifiableInterface, PingableInterface
{
    use HasUuids;

    protected $table = 'fcm_devices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'device_id',
        'token',
        'platform',
        'user_agent',
        'last_seen_at',
        'notifiable_type',
        'notifiable_id',
    ];

    protected $casts = [
        'last_seen_at' => 'immutable_datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        $collection->add(new NotificationRouteVO(
            channelClass: FirebaseCloudMessagingChannel::class,
            destination: (string) $this->token,
            metadata: new StrictDataObject([
                'type' => 'fcm',
                'device_id' => (string) $this->device_id,
            ]),
        ));

        return $collection;
    }

    public function ping(): PingStatus
    {
        return app(FcmPingPong::class)->ping($this);
    }

    public function isAlive(): bool
    {
        return app(FcmPingPong::class)->isAlive($this);
    }

    public function pingOrPrune(): PingStatus
    {
        return app(FcmPingPong::class)->pingOrPrune($this);
    }
}
