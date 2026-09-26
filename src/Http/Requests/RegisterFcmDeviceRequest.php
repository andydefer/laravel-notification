<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Http\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Enums\FcmPlatform;
use AndyDefer\LaravelNotification\Records\RegisterFcmDeviceRecord;
use Illuminate\Validation\Rule;

final class RegisterFcmDeviceRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'uuid'],
            'token' => ['required', 'string', 'min:100', 'max:512'],
            'platform' => ['nullable', 'string', Rule::enum(FcmPlatform::class)],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        $platform = $this->input('platform');

        return RegisterFcmDeviceRecord::from([
            'device_id' => (string) $this->input('device_id'),
            'token' => (string) $this->input('token'),
            'platform' => is_string($platform)
                ? FcmPlatform::tryFrom($platform)
                : null,
            'user_agent' => $this->userAgent(),
        ]);
    }
}
