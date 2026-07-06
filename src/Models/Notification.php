<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Models;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Notification extends Model
{
    use SoftDeletes;

    protected $table = 'notifications';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'session_id',
        'channel',
        'destination',
        'notifiable_type',
        'notifiable_id',
        'message',
        'metadata', // ✅ NOUVEAU
        'status',
        'error',
        'sent_at',
        'read_at',
    ];

    protected $casts = [
        'message' => 'array',
        'metadata' => 'array', // ✅ NOUVEAU
        'status' => NotificationStatus::class,
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function getId(): string
    {
        return (string) $this->id;
    }

    public function getSessionId(): ?string
    {
        return $this->session_id;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function getNotifiableType(): string
    {
        return $this->notifiable_type;
    }

    public function getNotifiableId(): int
    {
        return $this->notifiable_id;
    }

    public function getMessage(): ?NotificationMessageVO
    {
        $data = $this->message ?? [];

        if (empty($data)) {
            return null;
        }

        return NotificationMessageVO::from($data);
    }

    public function getMetadata(): StrictDataObject // ✅ NOUVEAU
    {
        $data = $this->metadata ?? [];

        return new StrictDataObject($data);
    }

    public function getBody(): string
    {
        $message = $this->getMessage();

        return $message?->getBodyValue();
    }

    public function getSubject(): string
    {
        $message = $this->getMessage();

        return $message?->getSubjectValue();
    }

    public function getType(): string
    {
        $message = $this->getMessage();

        return $message?->getType() ?? 'default';
    }

    public function getData(): StrictDataObject
    {
        $message = $this->getMessage();

        return $message?->getData() ?? new StrictDataObject([]);
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getSentAt(): ?DateTimeVO
    {
        return $this->sent_at ? DateTimeVO::from($this->sent_at) : null;
    }

    public function getReadAt(): ?DateTimeVO
    {
        return $this->read_at ? DateTimeVO::from($this->read_at) : null;
    }

    public function getCreatedAt(): ?DateTimeVO
    {
        return $this->created_at ? DateTimeVO::from($this->created_at) : null;
    }

    public function getUpdatedAt(): ?DateTimeVO
    {
        return $this->updated_at ? DateTimeVO::from($this->updated_at) : null;
    }

    public function getDeletedAt(): ?DateTimeVO
    {
        return $this->deleted_at ? DateTimeVO::from($this->deleted_at) : null;
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isSent(): bool
    {
        return $this->status === NotificationStatus::SENT;
    }
}
