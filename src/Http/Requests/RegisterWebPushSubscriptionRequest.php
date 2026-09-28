<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Http\Requests;

use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Records\RegisterWebPushSubscriptionRecord;
use AndyDefer\Nemesis\Contracts\Services\AgentServiceInterface;

final class RegisterWebPushSubscriptionRequest extends AbstractRequest
{
    public function __construct(
        private readonly AgentServiceInterface $agent,
    ) {
        parent::__construct();
    }

    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'url', 'max:500'],
            'p256dh' => ['required', 'string', 'max:255'],
            'auth' => ['required', 'string', 'max:255'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        $data = $this->validated();

        $this->agent->setUserAgent($this->userAgent() ?? '');

        return RegisterWebPushSubscriptionRecord::from([
            'endpoint' => $data['endpoint'],
            'p256dh' => $data['p256dh'],
            'auth' => $data['auth'],
            'browser' => $this->agent->browser(),
            'user_agent' => $this->userAgent(),
        ]);
    }
}
