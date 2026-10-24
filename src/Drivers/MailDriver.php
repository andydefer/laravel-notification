<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Driver d'envoi d'emails via le facade `Mail` de Laravel.
 *
 * La route porte UNIQUEMENT la destination (`$to`).
 * Toutes les autres options (attachments, cc, bcc, reply_to, priority,
 * from override) viennent de `$message->getMetadata()`.
 */
final class MailDriver extends AbstractDriver
{
    // ⬇️ Clés lues dans `$message->getMetadata()`
    private const ATTACHMENTS_KEY = 'attachments';

    private const CC_KEY = 'cc';

    private const BCC_KEY = 'bcc';

    private const REPLY_TO_KEY = 'reply_to';

    private const PRIORITY_KEY = 'priority';

    private const FROM_KEY = 'from';

    private const FROM_NAME_KEY = 'from_name';

    public function __construct(
        private readonly MailConfigRecord $config,
    ) {}

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        if (! $this->validateConfiguration()) {
            throw new RuntimeException('Mail configuration is incomplete.');
        }

        $to = $route->getDestination();

        if (! is_string($to) || $to === '') {
            throw new RuntimeException('Mail destination not specified.');
        }

        $subject = $message->getSubjectValue();
        $body = $message->getBodyValue();

        /** @var StrictDataObject|null $metadata */
        $metadata = $message->getMetadata();

        $attachments = $this->resolveAttachments($metadata);
        $cc = $this->resolveRecipients($metadata, self::CC_KEY);
        $bcc = $this->resolveRecipients($metadata, self::BCC_KEY);
        $replyTo = $this->resolveString($metadata, self::REPLY_TO_KEY);
        $priority = $this->resolvePriority($metadata);
        $from = $this->resolveString($metadata, self::FROM_KEY)
            ?? $this->config->default_from;
        $fromName = $this->resolveString($metadata, self::FROM_NAME_KEY)
            ?? $this->config->default_from_name;

        Mail::send([], [], function ($mailMessage) use (
            $to, $subject, $body, $from, $fromName,
            $attachments, $cc, $bcc, $replyTo, $priority,
        ) {
            if ($from !== null) {
                $mailMessage->from($from, $fromName);
            }

            $mailMessage->to($to)
                ->subject($subject)
                ->html($body);

            foreach ($cc as $address) {
                $mailMessage->cc($address);
            }

            foreach ($bcc as $address) {
                $mailMessage->bcc($address);
            }

            if ($replyTo !== null) {
                $mailMessage->replyTo($replyTo);
            }

            if ($priority !== null) {
                $mailMessage->priority($priority);
            }

            // ⬇️ Attachments : chemins locaux OU URLs, format Mail Laravel
            foreach ($attachments as $attachment) {
                $mailMessage->attach(
                    $attachment['path'],
                    $attachment['options'],
                );
            }
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

    /**
     * Résout la liste des pièces jointes depuis `metadata.attachments`.
     *
     * Formats acceptés :
     *   - String  → ['/path/to/file.pdf']
     *   - Array   → ['path' => '/x.pdf', 'as' => 'x.pdf', 'mime' => 'application/pdf']
     *   - Array de strings ou d'arrays
     *
     * @return array<int, array{path: string, options: array<string, string>}>
     */
    private function resolveAttachments(?StrictDataObject $metadata): array
    {
        $raw = $metadata?->get(self::ATTACHMENTS_KEY);

        if ($raw === null) {
            return [];
        }

        if (is_string($raw)) {
            return [['path' => $raw, 'options' => []]];
        }

        if (! is_array($raw)) {
            return [];
        }

        $attachments = [];

        foreach ($raw as $entry) {
            if (is_string($entry) && $entry !== '') {
                $attachments[] = ['path' => $entry, 'options' => []];

                continue;
            }

            if (! is_array($entry) || ! isset($entry['path']) || ! is_string($entry['path'])) {
                continue;
            }

            $options = [];
            if (isset($entry['as']) && is_string($entry['as'])) {
                $options['as'] = $entry['as'];
            }
            if (isset($entry['mime']) && is_string($entry['mime'])) {
                $options['mime'] = $entry['mime'];
            }

            $attachments[] = ['path' => $entry['path'], 'options' => $options];
        }

        return $attachments;
    }

    /**
     * Résout `cc` ou `bcc` (string ou array de strings).
     *
     * @return array<int, string>
     */
    private function resolveRecipients(?StrictDataObject $metadata, string $key): array
    {
        $raw = $metadata?->get($key);

        if (is_string($raw) && $raw !== '') {
            return [$raw];
        }

        if (is_array($raw)) {
            return array_values(
                array_filter(
                    $raw,
                    static fn (mixed $value): bool => is_string($value) && $value !== '',
                ),
            );
        }

        return [];
    }

    /**
     * Résout une string optionnelle.
     */
    private function resolveString(?StrictDataObject $metadata, string $key): ?string
    {
        $value = $metadata?->get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Résout la priorité (1 = haute, 3 = normale, 5 = basse).
     */
    private function resolvePriority(?StrictDataObject $metadata): ?int
    {
        $value = $metadata?->get(self::PRIORITY_KEY);

        if (! is_int($value) || $value < 1 || $value > 5) {
            return null;
        }

        return $value;
    }
}
