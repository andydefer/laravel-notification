<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Database\Eloquent\Model;

final class TestDoctor extends Model implements NotifiableInterface
{
    protected $table = 'test_doctors';

    protected $fillable = [
        'name',
        'primary_email',
        'secondary_email',
        'phone',
        'specialty',
    ];

    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        // ✅ Canal Mail avec email principal
        if ($this->primary_email) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: MailChannel::class,
                    destination: $this->primary_email,
                    metadata: new StrictDataObject([
                        'name' => $this->name,
                        'type' => 'primary',
                    ])
                )
            );
        }

        // ✅ Canal Mail avec email secondaire
        if ($this->secondary_email) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: MailChannel::class,
                    destination: $this->secondary_email,
                    metadata: new StrictDataObject([
                        'name' => $this->name,
                        'type' => 'secondary',
                    ])
                )
            );
        }

        // ✅ Canal Database
        $collection->add(
            new NotificationRouteVO(
                channelClass: DatabaseChannel::class,
                destination: 'database',
                metadata: new StrictDataObject([
                    'type' => 'database',
                    'specialty' => $this->specialty,
                ])
            )
        );

        if ($this->phone) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: SmsChannel::class,
                    destination: $this->phone,
                )
            );
        }

        return $collection;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }

    public function getKey(): int
    {
        return $this->id;
    }
}
