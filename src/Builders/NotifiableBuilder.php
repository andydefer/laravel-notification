<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Builders;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Collections\SendResultCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Records\SendAtRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\DirectNotifiable;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use InvalidArgumentException;

final class NotifiableBuilder
{
    private NotificationRouteCollection $routes;

    private ?MessageBodyVO $body = null;

    private ?MessageSubjectVO $subject = null;

    private string $type = 'default';

    private StrictDataObject $data;

    private ?SendOptions $options = null;

    private string $morphClass = 'direct';

    private int|string $key = 0;

    private NotificationService $service;

    public function __construct(?NotificationService $service = null)
    {
        $this->service = $service ?? app(NotificationService::class);
        $this->routes = new NotificationRouteCollection;
        $this->data = new StrictDataObject([]);
    }

    public static function create(?NotificationService $service = null): self
    {
        return new self($service);
    }

    /**
     * Set destination for a specific channel.
     */
    public function to(string $channelClass, string|array $destination): self
    {
        $destinations = is_array($destination) ? $destination : [$destination];

        if (empty($destinations)) {
            throw new InvalidArgumentException('Destination cannot be empty.');
        }

        // ✅ Supprimer les routes existantes pour ce canal
        $newRoutes = new NotificationRouteCollection;
        foreach ($this->routes as $route) {
            if ($route->getChannelClass() !== $channelClass) {
                $newRoutes->add($route);
            }
        }
        $this->routes = $newRoutes;

        foreach ($destinations as $dest) {
            if (empty($dest)) {
                throw new InvalidArgumentException('Destination cannot be empty.');
            }
            $this->routes->add(new NotificationRouteVO(
                channelClass: $channelClass,
                destination: $dest,
            ));
        }

        return $this;
    }

    /**
     * Set the message body.
     */
    public function body(string $body): self
    {
        $this->body = new MessageBodyVO($body);

        return $this;
    }

    /**
     * Set the message subject.
     */
    public function subject(string $subject): self
    {
        $this->subject = new MessageSubjectVO($subject);

        return $this;
    }

    /**
     * Set the message type.
     */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set additional data.
     */
    public function data(array $data): self
    {
        $this->data = new StrictDataObject($data);

        return $this;
    }

    /**
     * Set send options.
     */
    public function options(SendOptions $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Set limit per channel.
     */
    public function limit(int $limit): self
    {
        if ($this->options === null) {
            $this->options = SendOptions::init();
        }
        $this->options = $this->options->withLimitPerChannel($limit);

        return $this;
    }

    /**
     * Add a destination filter.
     */
    public function filter(string $channelClass, string|array $destinations): self
    {
        if ($this->options === null) {
            $this->options = SendOptions::init();
        }
        $this->options = $this->options->withDestinationFilter($channelClass, $destinations);

        return $this;
    }

    /**
     * Set all filters.
     */
    public function filters(array $filters): self
    {
        if ($this->options === null) {
            $this->options = SendOptions::init();
        }
        $this->options = $this->options->withDestinationFilters($filters);

        return $this;
    }

    /**
     * Set metadata for a specific channel.
     */
    public function metadata(string $channelClass, StrictDataObject $metadata): self
    {
        $newRoutes = new NotificationRouteCollection;
        foreach ($this->routes as $route) {
            if ($route->getChannelClass() === $channelClass) {
                $newRoutes->add(new NotificationRouteVO(
                    channelClass: $channelClass,
                    destination: $route->getDestination(),
                    metadata: $metadata,
                ));
            } else {
                $newRoutes->add($route);
            }
        }
        $this->routes = $newRoutes;

        return $this;
    }

    /**
     * Set metadata for all channels.
     */
    public function metadataAll(StrictDataObject $metadata): self
    {
        $newRoutes = new NotificationRouteCollection;
        foreach ($this->routes as $route) {
            $newRoutes->add(new NotificationRouteVO(
                channelClass: $route->getChannelClass(),
                destination: $route->getDestination(),
                metadata: $metadata,
            ));
        }
        $this->routes = $newRoutes;

        return $this;
    }

    /**
     * Set morph class and key for tracing.
     */
    public function as(string $morphClass, int|string $key = 0): self
    {
        $this->morphClass = $morphClass;
        $this->key = $key;

        return $this;
    }

    /**
     * Send immediately.
     */
    public function sendNow(?SendNowRecord $record = null): SendResultCollection
    {
        $notifiable = $this->buildNotifiable();
        $message = $this->buildMessage();

        if ($this->options !== null) {
            $this->service->withOptions($this->options);
        }

        $results = $this->service->sendNow($notifiable, $message, $record);

        $this->service->resetOptions();
        // ✅ Aussi réinitialiser les options locales
        $this->options = null;

        return $results;
    }

    /**
     * Send after a delay.
     */
    public function sendLater(int $delaySeconds = 60): TaskAliasVO
    {
        $notifiable = $this->buildNotifiable();
        $message = $this->buildMessage();

        $record = new SendLaterRecord(delay_seconds: $delaySeconds);

        if ($this->options !== null) {
            $this->service->withOptions($this->options);
        }

        $alias = $this->service->sendLater($notifiable, $message, $record);

        $this->service->resetOptions();
        $this->options = null;

        return $alias;
    }

    /**
     * Send at a specific time.
     */
    public function sendAt(NotificationDateTimeVO $scheduledAt): TaskAliasVO
    {
        $notifiable = $this->buildNotifiable();
        $message = $this->buildMessage();

        $record = new SendAtRecord(scheduled_at: $scheduledAt);

        if ($this->options !== null) {
            $this->service->withOptions($this->options);
        }

        $alias = $this->service->sendAt($notifiable, $message, $record);

        $this->service->resetOptions();
        $this->options = null;

        return $alias;
    }

    /**
     * Send on a recurring schedule.
     */
    public function sendRecurring(
        int $intervalSeconds,
        NotificationDateTimeVO $startAt,
        ?NotificationDateTimeVO $endAt = null
    ): TaskAliasVO {
        $notifiable = $this->buildNotifiable();
        $message = $this->buildMessage();

        $record = new SendRecurringRecord(
            interval_seconds: $intervalSeconds,
            start_at: $startAt,
            end_at: $endAt,
        );

        if ($this->options !== null) {
            $this->service->withOptions($this->options);
        }

        $alias = $this->service->sendRecurring($notifiable, $message, $record);

        $this->service->resetOptions();
        $this->options = null;

        return $alias;
    }

    /**
     * Reset the builder state.
     */
    public function reset(): self
    {
        $this->routes = new NotificationRouteCollection;
        $this->body = null;
        $this->subject = null;
        $this->type = 'default';
        $this->data = new StrictDataObject([]);
        $this->options = null;
        $this->morphClass = 'direct';
        $this->key = 0;

        return $this;
    }

    /**
     * Build the notifiable.
     */
    private function buildNotifiable(): NotifiableInterface
    {
        $notifiable = new DirectNotifiable($this->routes);
        $notifiable->setMorphClass($this->morphClass);
        $notifiable->setKey($this->key);

        return $notifiable;
    }

    /**
     * Build the message.
     */
    private function buildMessage(): NotificationMessageVO
    {
        if ($this->body === null) {
            throw new \RuntimeException('Message body is required. Call body() first.');
        }

        if ($this->subject === null) {
            throw new \RuntimeException('Message subject is required. Call subject() first.');
        }

        return new NotificationMessageVO(
            body: $this->body,
            subject: $this->subject,
            type: $this->type,
            data: $this->data,
        );
    }
}
