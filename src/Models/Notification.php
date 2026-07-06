<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Models;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Notification model for storing notification records.
 *
 * Represents a notification sent to a notifiable entity through a specific channel.
 * Stores the message content, status, metadata and delivery information.
 *
 * @property string $id
 * @property string $session_id
 * @property string $channel
 * @property string $destination
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property array<string, mixed> $message
 * @property array<string, mixed> $metadata
 * @property NotificationStatus $status
 * @property string|null $error
 * @property \DateTimeInterface|null $sent_at
 * @property \DateTimeInterface|null $read_at
 * @property \DateTimeInterface|null $created_at
 * @property \DateTimeInterface|null $updated_at
 * @property \DateTimeInterface|null $deleted_at
 */
final class Notification extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'session_id',
        'channel',
        'destination',
        'notifiable_type',
        'notifiable_id',
        'message',
        'metadata',
        'status',
        'error',
        'sent_at',
        'read_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'message' => 'array',
        'metadata' => 'array',
        'status' => NotificationStatus::class,
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /**
     * Get the notifiable entity that owns the notification.
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the notification ID.
     */
    public function getId(): string
    {
        return (string) $this->id;
    }

    /**
     * Get the session ID.
     */
    public function getSessionId(): ?string
    {
        return $this->session_id;
    }

    /**
     * Get the channel name.
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * Get the destination address.
     */
    public function getDestination(): string
    {
        return $this->destination;
    }

    /**
     * Get the notifiable type (class name).
     */
    public function getNotifiableType(): string
    {
        return $this->notifiable_type;
    }

    /**
     * Get the notifiable ID.
     */
    public function getNotifiableId(): int
    {
        return (int) $this->notifiable_id;
    }

    /**
     * Get the notification message.
     */
    public function getMessage(): ?NotificationMessageVO
    {
        $data = $this->message ?? [];

        if (empty($data)) {
            return null;
        }

        return NotificationMessageVO::from($data);
    }

    /**
     * Get the notification metadata.
     */
    public function getMetadata(): StrictDataObject
    {
        $data = $this->metadata ?? [];

        return StrictDataObject::from($data);
    }

    /**
     * Get the message body.
     */
    public function getBody(): string
    {
        $message = $this->getMessage();

        return $message?->getBodyValue() ?? '';
    }

    /**
     * Get the message subject.
     */
    public function getSubject(): string
    {
        $message = $this->getMessage();

        return $message?->getSubjectValue() ?? '';
    }

    /**
     * Get the message type.
     */
    public function getType(): string
    {
        $message = $this->getMessage();

        return $message?->getType() ?? 'default';
    }

    /**
     * Get the message data.
     */
    public function getData(): StrictDataObject
    {
        $message = $this->getMessage();

        return $message?->getData() ?? StrictDataObject::from([]);
    }

    /**
     * Get the notification status.
     */
    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    /**
     * Get the error message if any.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get the sent at timestamp.
     */
    public function getSentAt(): ?DateTimeVO
    {
        return $this->sent_at ? DateTimeVO::from($this->sent_at) : null;
    }

    /**
     * Get the read at timestamp.
     */
    public function getReadAt(): ?DateTimeVO
    {
        return $this->read_at ? DateTimeVO::from($this->read_at) : null;
    }

    /**
     * Get the created at timestamp.
     */
    public function getCreatedAt(): ?DateTimeVO
    {
        return $this->created_at ? DateTimeVO::from($this->created_at) : null;
    }

    /**
     * Get the updated at timestamp.
     */
    public function getUpdatedAt(): ?DateTimeVO
    {
        return $this->updated_at ? DateTimeVO::from($this->updated_at) : null;
    }

    /**
     * Get the deleted at timestamp.
     */
    public function getDeletedAt(): ?DateTimeVO
    {
        return $this->deleted_at ? DateTimeVO::from($this->deleted_at) : null;
    }

    /**
     * Check if the notification has been read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Check if the notification has been sent.
     */
    public function isSent(): bool
    {
        return $this->status === NotificationStatus::SENT;
    }
}
