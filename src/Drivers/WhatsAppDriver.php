<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\WhatsAppConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use RuntimeException;

/**
 * Driver for sending WhatsApp notifications.
 *
 * Sends messages to WhatsApp users using the Meta Business API.
 * Requires a valid access token and phone number ID.
 */
final class WhatsAppDriver extends AbstractDriver
{
    /**
     * Constructor for the WhatsApp driver.
     *
     * @param  WhatsAppConfigRecord  $config  The WhatsApp configuration
     */
    public function __construct(
        private readonly WhatsAppConfigRecord $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $to = $route->getDestination();

        if (empty($to)) {
            throw new RuntimeException('WhatsApp destination not specified.');
        }

        // TODO: Implement actual WhatsApp provider integration
        // Example with Meta API:
        // $client = new Meta\WhatsApp\Client($this->config->access_token);
        // $client->sendMessage($to, [
        //     'from' => $this->config->phone_number_id,
        //     'body' => $message->getBodyValue()
        // ]);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getChannel(): string
    {
        return 'whatsapp';
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && $this->config->access_token !== null
            && $this->config->phone_number_id !== null;
    }
}
