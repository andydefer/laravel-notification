<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Minishlink\WebPush\VAPID;
use Throwable;

class GenerateVapidKeysDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'notification:generate-vapid {env=.env}#"Path to the env file" {--force}#"Overwrite existing VAPID keys"';
    }

    public function getDescription(): string
    {
        return 'Generate a VAPID key pair for Web Push';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from([
            'notification:gvk',
            'n:gvk',
        ]);
    }

    protected function execute(): ExitCode
    {
        $envFile = (string) $this->getArgument('env');

        if ($envFile === '') {
            $envFile = '.env';
        }

        $envPath = str_starts_with($envFile, '/')
            ? $envFile
            : base_path($envFile);

        if (! is_file($envPath)) {
            $this->error(sprintf('Env file not found at "%s".', $envPath));

            return ExitCode::FAILURE;
        }

        $env = file_get_contents($envPath);

        if ($env === false) {
            $this->error(sprintf('Unable to read env file at "%s".', $envPath));

            return ExitCode::FAILURE;
        }

        $publicKeyExists = preg_match('/^WEBPUSH_PUBLIC_KEY=/m', $env) === 1;
        $privateKeyExists = preg_match('/^WEBPUSH_PRIVATE_KEY=/m', $env) === 1;

        if (! $this->getFlag('force') && ($publicKeyExists || $privateKeyExists)) {
            $this->warn('VAPID keys already exist in env file. Use --force to overwrite.');

            return ExitCode::SUCCESS;
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $e) {
            $this->error('VAPID generation failed: '.$e->getMessage());

            return ExitCode::FAILURE;
        }

        $publicKey = $keys['publicKey'];
        $privateKey = $keys['privateKey'];

        $env = $this->upsertEnv($env, 'WEBPUSH_PUBLIC_KEY', $publicKey);
        $env = $this->upsertEnv($env, 'WEBPUSH_PRIVATE_KEY', $privateKey);

        if (preg_match('/^WEBPUSH_NOTIFICATION_ENABLED=/m', $env) !== 1) {
            $env = rtrim($env, PHP_EOL).PHP_EOL.'WEBPUSH_NOTIFICATION_ENABLED=true';
        }

        if (preg_match('/^WEBPUSH_SUBJECT=/m', $env) !== 1) {
            $env = rtrim($env, PHP_EOL).PHP_EOL.'WEBPUSH_SUBJECT="mailto:contact@afya-medical.com"';
        }

        file_put_contents($envPath, $env);

        $this->newLine();
        $this->info('VAPID keys generated and written to '.$envPath);
        $this->line('WEBPUSH_PUBLIC_KEY  = '.$publicKey);
        $this->line('WEBPUSH_PRIVATE_KEY = '.$privateKey);
        $this->newLine();
        $this->warn('Do not commit the private key. Keep it secret.');

        return ExitCode::SUCCESS;
    }

    private function upsertEnv(string $env, string $key, string $value): string
    {
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $env) === 1) {
            return (string) preg_replace($pattern, $key.'='.$value, $env);
        }

        return rtrim($env, PHP_EOL).PHP_EOL.$key.'='.$value.PHP_EOL;
    }
}
