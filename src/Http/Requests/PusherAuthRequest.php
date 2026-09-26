<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Http\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Records\PusherAuthRecord;

final class PusherAuthRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'socket_id' => ['required', 'string', 'max:255'],
            'channel_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return PusherAuthRecord::from([
            'socket_id' => (string) $this->input('socket_id'),
            'channel_name' => (string) $this->input('channel_name'),
        ]);
    }
}
