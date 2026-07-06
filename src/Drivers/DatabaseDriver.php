<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

/**
 * Driver for storing notifications in the database.
 *
 * Saves notification records to the database for later retrieval
 * and audit purposes.
 */
final class DatabaseDriver extends AbstractDriver
{
    /**
     * Constructor for the database driver.
     *
     * @param  DatabaseConfigRecord  $config  The database configuration
     */
    public function __construct(
        private readonly DatabaseConfigRecord $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        // TODO: Implement database storage logic
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getChannel(): string
    {
        return 'database';
    }

    /**
     * {@inheritDoc}
     */
    public function validateConfiguration(): bool
    {
        return $this->config->table !== '';
    }
}
