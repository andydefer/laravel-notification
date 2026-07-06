<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Abstracts;

use AndyDefer\LaravelNotification\Contracts\DriverInterface;
use AndyDefer\LaravelNotification\Records\SendResultRecord;
use AndyDefer\LaravelNotification\ValueObjects\ErrorMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

abstract class AbstractDriver implements DriverInterface
{
    final public function send(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): SendResultRecord {
        $this->before($message, $route);

        try {
            $result = $this->execute($message, $route);
            $this->after($message, $route, $result, null);

            return new SendResultRecord(
                channel: new FqcnChannelVO($route->getChannelClass()),
                destination: $route->getDestination(),
                success: $result,
            );
        } catch (\Exception $e) {
            $errorMessage = sprintf('[%s] - %s', get_class($e), $e->getMessage());

            return new SendResultRecord(
                channel: new FqcnChannelVO($route->getChannelClass()),
                destination: $route->getDestination(),
                success: false,
                error_message: new ErrorMessageVO($errorMessage),
            );
        }
    }

    protected function before(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): void {
        if (! $this->validateConfiguration()) {
            throw new \RuntimeException(
                sprintf('Driver %s configuration is invalid.', static::class)
            );
        }
    }

    protected function after(
        NotificationMessageVO $message,
        NotificationRouteVO $route,
        bool $success,
        ?\Exception $error = null
    ): void {
        // Logique facultative après l'envoi
    }

    abstract protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool;

    abstract public function getChannel(): string;

    public function validateConfiguration(): bool
    {
        return true;
    }
}
