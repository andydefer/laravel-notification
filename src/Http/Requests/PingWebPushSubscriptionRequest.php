<?php

// src/Http/Requests/PingWebPushSubscriptionRequest.php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Http\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Records\PingWebPushSubscriptionRecord;

final class PingWebPushSubscriptionRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'subscription_id' => ['required', 'string'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return PingWebPushSubscriptionRecord::from([
            'subscription_id' => (string) $this->input('subscription_id'),
        ]);
    }
}
