<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\SmsConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class SmsDriver extends AbstractDriver
{
    public function __construct(
        private readonly SmsConfigRecord $config,
    ) {}

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $to = $route->getDestination();

        if (empty($to)) {
            throw new \RuntimeException('SMS destination not specified.');
        }

        // Simulation d'envoi (remplacer par Twilio, Vonage, etc.)
        // $client = new Twilio\Rest\Client($this->config->sid, $this->config->token);
        // $client->messages->create($to, [
        //     'from' => $this->config->from,
        //     'body' => $message->getBodyValue()
        // ]);

        return true;
    }

    public function getChannel(): string
    {
        return 'sms';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && $this->config->sid !== null
            && $this->config->token !== null
            && $this->config->from !== null;
    }
}
