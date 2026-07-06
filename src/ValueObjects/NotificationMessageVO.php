<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

class NotificationMessageVO extends AbstractValueObject
{
    public readonly MessageBodyVO $body;

    public readonly MessageSubjectVO $subject;

    public readonly string $type;

    public readonly StrictDataObject $data;

    public function __construct(
        MessageBodyVO $body,
        MessageSubjectVO $subject,
        string $type = 'default',
        ?StrictDataObject $data = null,
    ) {
        $this->body = $body;
        $this->subject = $subject;
        $this->type = $type;
        $this->data = $data ?? new StrictDataObject([]);
    }

    public function getBody(): MessageBodyVO
    {
        return $this->body;
    }

    public function getSubject(): MessageSubjectVO
    {
        return $this->subject;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): StrictDataObject
    {
        return $this->data;
    }

    public function getBodyValue(): string
    {
        return $this->body->getValue();
    }

    public function getSubjectValue(): string
    {
        return $this->subject->getValue();
    }

    public function toArray(): array
    {
        return [
            'body' => $this->body->getValue(),
            'subject' => $this->subject->getValue(),
            'type' => $this->type,
            'data' => $this->data->toArray(),
        ];
    }

    public function with(string $key, mixed $value): self
    {
        $newData = $this->data->toArray();
        $newData[$key] = $value;

        return new self(
            body: $this->body,
            subject: $this->subject,
            type: $this->type,
            data: new StrictDataObject($newData),
        );
    }

    public function has(string $key): bool
    {
        return $this->data->has($key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data->get($key, $default);
    }

    public function getValue(): string
    {
        return json_encode($this->toArray());
    }
}
