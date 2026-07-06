<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\SmsConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use RuntimeException;

/**
 * Driver for sending SMS notifications.
 *
 * Sends text messages to phone numbers using SMS providers like Twilio or Vonage.
 * Requires valid provider credentials and a sender phone number.
 */
final class SmsDriver extends AbstractDriver
{
    /**
     * Constructor for the SMS driver.
     *
     * @param  SmsConfigRecord  $config  The SMS configuration
     */
    public function __construct(
        private readonly SmsConfigRecord $config,
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
            throw new RuntimeException('SMS destination not specified.');
        }

        // TODO: Implement actual SMS provider integration
        // Example with Twilio:
        // $client = new Twilio\Rest\Client($this->config->sid, $this->config->token);
        // $client->messages->create($to, [
        //     'from' => $this->config->from,
        //     'body' => $message->getBodyValue()
        // ]);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getChannel(): string
    {
        return 'sms';
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && $this->config->sid !== null
            && $this->config->token !== null
            && $this->config->from !== null;
    }
}
