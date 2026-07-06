<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Contracts;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;

interface ChannelInterface
{
    public function getName(): string;

    public function getLabel(): string;

    public function getIcon(): string;

    public function isEnabled(): bool;

    public function createDriver(): AbstractDriver;

    /**
     * Valider la destination pour ce canal.
     * Ex: mail → email valide, sms → numéro de téléphone valide
     */
    public static function validateDestination(string $destination): bool;
}
