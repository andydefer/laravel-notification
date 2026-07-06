<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\WhatsAppConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class WhatsAppDriver extends AbstractDriver
{
    public function __construct(
        private readonly WhatsAppConfigRecord $config,
    ) {}

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $to = $route->getDestination();

        if (empty($to)) {
            throw new \RuntimeException('WhatsApp destination not specified.');
        }

        // Simulation d'envoi (remplacer par Meta API, Twilio, etc.)
        // $client = new Meta\WhatsApp\Client($this->config->access_token);
        // $client->sendMessage($to, [
        //     'from' => $this->config->phone_number_id,
        //     'body' => $message->getBodyValue()
        // ]);

        return true;
    }

    public function getChannel(): string
    {
        return 'whatsapp';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && $this->config->access_token !== null
            && $this->config->phone_number_id !== null;
    }
}
