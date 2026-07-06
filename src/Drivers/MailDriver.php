<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Support\Facades\Mail;

final class MailDriver extends AbstractDriver
{
    public function __construct(
        private readonly MailConfigRecord $config,
    ) {}

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $to = $route->getDestination();

        if (empty($to)) {
            throw new \RuntimeException('Mail destination not specified.');
        }

        $subject = $message->getSubjectValue();
        $body = $message->getBodyValue();

        Mail::send([], [], function ($mailMessage) use ($to, $subject, $body) {
            if ($this->config->default_from) {
                $mailMessage->from(
                    $this->config->default_from,
                    $this->config->default_from_name
                );
            }
            $mailMessage->to($to)
                ->subject($subject)
                ->html($body);
        });

        return true;
    }

    public function getChannel(): string
    {
        return 'mail';
    }

    public function validateConfiguration(): bool
    {

        return $this->config->enabled
            && ($this->config->default_from !== null);
    }
}
