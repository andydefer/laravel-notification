<?php

// src/Http/Requests/PingFcmDeviceRequest.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Http\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Records\PingFcmDeviceRecord;

final class PingFcmDeviceRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return PingFcmDeviceRecord::from([
            'device_id' => (string) $this->input('device_id'),
        ]);
    }
}
