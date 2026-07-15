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

final class TestUser extends Model implements NotifiableInterface
{
    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'email_secondary', // ✅ AJOUTÉ
        'phone',
    ];

    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        // ✅ Canal de test (toujours actif)
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

        // ✅ Email secondaire
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
                    channelClass: TestChannel::class,  // ✅ Utilise TestChannel au lieu de SmsChannel
                    destination: $this->phone,
                    metadata: new StrictDataObject(['type' => 'phone'])
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
