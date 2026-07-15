<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Tests\Fixtures\Models;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Tests\Fixtures\Channels\TestChannel;
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

        if ($this->primary_email) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: MailChannel::class,
                    destination: $this->primary_email,
                    metadata: new StrictDataObject([
                        'type' => 'primary',
                        'specialty' => $this->specialty ?? 'general',
                    ])
                )
            );
        }

        if ($this->secondary_email) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: MailChannel::class,
                    destination: $this->secondary_email,
                    metadata: new StrictDataObject([
                        'type' => 'secondary',
                        'specialty' => $this->specialty ?? 'general',
                    ])
                )
            );
        }

        $collection->add(
            new NotificationRouteVO(
                channelClass: DatabaseChannel::class,
                destination: 'database',
                metadata: new StrictDataObject([
                    'type' => 'database',
                    'specialty' => $this->specialty ?? 'general',
                ])
            )
        );

        // ✅ Utiliser TestChannel au lieu de SmsChannel
        if ($this->phone) {
            $collection->add(
                new NotificationRouteVO(
                    channelClass: TestChannel::class,
                    destination: $this->phone,
                    metadata: new StrictDataObject(['type' => 'phone'])
                )
            );
        }

        // ✅ Canal de test
        $collection->add(
            new NotificationRouteVO(
                channelClass: TestChannel::class,
                destination: 'test_doctor',
                metadata: new StrictDataObject(['type' => 'test'])
            )
        );

        return $collection;
    }

    public function getMorphClass(): string
    {
        return TestDoctor::class;
    }

    public function getKey(): int
    {
        return $this->id;
    }
}
