<?php

// database/factories/NotificationFactory.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Database\Factories;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Models\Notification;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\UuidVO;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
final class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Notification>
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO($this->faker->sentence()),
            subject: new MessageSubjectVO($this->faker->sentence()),
            type: $this->faker->randomElement(['welcome', 'payment', 'alert', 'info']),
            data: StrictDataObject::from([
                'key' => $this->faker->word(),
                'value' => $this->faker->word(),
            ]),
        );

        return [
            'id' => UuidVO::generate()->getValue(),
            'session_id' => UuidVO::generate()->getValue(),
            'channel' => MailChannel::class,
            'destination' => $this->faker->safeEmail(),
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $this->faker->numberBetween(1, 100),
            'message' => $message->toArray(),
            'metadata' => [
                'source' => 'factory',
                'generated_at' => now()->toIso8601String(),
            ],
            'status' => NotificationStatus::PENDING->value,
            'error' => null,
            'sent_at' => null,
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the notification is sent.
     */
    public function sent(): self
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::SENT->value,
            'sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the notification is delivered.
     */
    public function delivered(): self
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::DELIVERED->value,
            'sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the notification is failed.
     */
    public function failed(string $error = 'Delivery failed'): self
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::FAILED->value,
            'error' => $error,
        ]);
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): self
    {
        return $this->state(fn () => [
            'read_at' => now(),
        ]);
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): self
    {
        return $this->state(fn () => [
            'read_at' => null,
        ]);
    }

    /**
     * Use the mail channel.
     */
    public function mail(): self
    {
        return $this->state(fn () => [
            'channel' => MailChannel::class,
            'destination' => $this->faker->safeEmail(),
        ]);
    }

    /**
     * Use the database channel.
     */
    public function database(): self
    {
        return $this->state(fn () => [
            'channel' => DatabaseChannel::class,
            'destination' => 'database',
        ]);
    }

    /**
     * Use a specific channel.
     */
    public function channel(string $channelClass): self
    {
        return $this->state(fn () => [
            'channel' => $channelClass,
        ]);
    }

    /**
     * Set the destination.
     */
    public function to(string $destination): self
    {
        return $this->state(fn () => [
            'destination' => $destination,
        ]);
    }

    /**
     * Set the notifiable.
     */
    public function forNotifiable(string $type, int $id): self
    {
        return $this->state(fn () => [
            'notifiable_type' => $type,
            'notifiable_id' => $id,
        ]);
    }

    /**
     * Set the session id.
     */
    public function withSession(string $sessionId): self
    {
        return $this->state(fn () => [
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Set the message type.
     */
    public function type(string $type): self
    {
        return $this->state(function () use ($type) {
            $message = new NotificationMessageVO(
                body: new MessageBodyVO($this->faker->sentence()),
                subject: new MessageSubjectVO($this->faker->sentence()),
                type: $type,
                data: StrictDataObject::from([]),
            );

            return [
                'message' => $message->toArray(),
            ];
        });
    }

    /**
     * Set the message body.
     */
    public function body(string $body): self
    {
        return $this->state(function () use ($body) {
            $message = new NotificationMessageVO(
                body: new MessageBodyVO($body),
                subject: new MessageSubjectVO($this->faker->sentence()),
                type: 'test',
                data: StrictDataObject::from([]),
            );

            return [
                'message' => $message->toArray(),
            ];
        });
    }

    /**
     * Set the message subject.
     */
    public function subject(string $subject): self
    {
        return $this->state(function () use ($subject) {
            $message = new NotificationMessageVO(
                body: new MessageBodyVO($this->faker->sentence()),
                subject: new MessageSubjectVO($subject),
                type: 'test',
                data: StrictDataObject::from([]),
            );

            return [
                'message' => $message->toArray(),
            ];
        });
    }

    /**
     * Set the metadata.
     */
    public function metadata(array $metadata): self
    {
        return $this->state(fn () => [
            'metadata' => $metadata,
        ]);
    }
}
