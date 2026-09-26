<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Tests\Fixtures\Channels\TestChannel;
use AndyDefer\LaravelNotification\Traits\HasNotifications;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\Nemesis\Contracts\MustNemesis;
use Illuminate\Database\Eloquent\Model;

final class TestUser extends Model implements MustNemesis, NotifiableInterface
{
    use HasNotifications;

    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'email_secondary',
        'phone',
    ];

    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        $collection->add(
            new NotificationRouteVO(
                channelClass: TestChannel::class,
                destination: 'test_destination',
                metadata: new StrictDataObject(['type' => 'test'])
            )
        );

        if ($this->email) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: MailChannel::class,
                    destination: $this->email,
                    metadata: new StrictDataObject(['name' => $this->name])
                )
            );
        }

        if ($this->email_secondary) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: MailChannel::class,
                    destination: $this->email_secondary,
                    metadata: new StrictDataObject(['name' => $this->name, 'type' => 'secondary'])
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
                    channelClass: TestChannel::class,
                    destination: $this->phone,
                    metadata: new StrictDataObject(['type' => 'phone'])
                )
            );
        }

        return $collection;
    }

    public function nemesisFormat(): AbstractData
    {
        return new class($this->getKey()) extends AbstractData
        {
            public function __construct(public readonly int|string|null $id) {}
        };
    }
}
