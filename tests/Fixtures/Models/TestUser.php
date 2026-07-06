<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Channels\WhatsAppChannel;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Database\Eloquent\Model;

final class TestUser extends Model implements NotifiableInterface
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'phone',
    ];

    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        if ($this->email) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: MailChannel::class,
                    destination: $this->email,
                    metadata: new StrictDataObject(['name' => $this->name])
                )
            );
        }

        $collection->add(
            new NotificationRouteVO(
                channelClass: DatabaseChannel::class,
                destination: 'database',
                metadata: new StrictDataObject(['type' => 'database'])
            )
        );

        if ($this->phone) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: SmsChannel::class,
                    destination: $this->phone,
                )
            );
            $collection->add(
                new NotificationRouteVO(
                    channelClass: WhatsAppChannel::class,
                    destination: $this->phone,
                )
            );
        }

        return $collection;
    }

    public function getMorphClass(): string
    {
        return TestUser::class;
    }

    public function getKey(): int
    {
        return $this->id;
    }
}
