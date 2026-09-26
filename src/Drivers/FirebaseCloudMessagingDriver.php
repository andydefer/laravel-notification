<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\FirebaseConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Google\Client as GoogleClient;
use Google\Exception as GoogleException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use stdClass;

final class FirebaseCloudMessagingDriver extends AbstractDriver
{
    private const TITLE_KEY = 'title';

    private const DATA_KEY = 'data';

    private const DEFAULT_TITLE = 'Notification';

    private ?string $accessToken = null;

    public function __construct(private readonly FirebaseConfigRecord $config) {}

    public function getChannel(): string
    {
        return 'firebase';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->enabled
            && ! empty($this->config->credentials_path)
            && is_file((string) $this->config->credentials_path);
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        if (! $this->validateConfiguration()) {
            throw new RuntimeException('Firebase configuration is incomplete.');
        }

        $projectId = $this->resolveProjectId();
        $deviceToken = $route->getDestination();

        if (! is_string($deviceToken) || $deviceToken === '') {
            throw new RuntimeException('Firebase device token is missing.');
        }

        $data = $this->resolveData($message, $route);

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $this->resolveTitle($message, $route),
                    'body' => $message->getBodyValue(),
                ],
                // FCM requires `data` to be a JSON object (map), never a list.
                // An empty PHP array encodes to `[]`, which FCM rejects.
                'data' => $data === [] ? new stdClass : $data,
            ],
        ];

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout($this->config->timeout)
            ->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                $payload,
            );

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Firebase trigger failed (HTTP %d): %s',
                $response->status(),
                $response->body(),
            ));
        }

        return true;
    }

    private function resolveProjectId(): string
    {
        if (is_string($this->config->project_id) && $this->config->project_id !== '') {
            return $this->config->project_id;
        }

        $credentials = $this->loadCredentials();

        if (! isset($credentials['project_id']) || ! is_string($credentials['project_id'])) {
            throw new RuntimeException('Firebase project_id is missing from credentials.');
        }

        return $credentials['project_id'];
    }

    private function resolveTitle(NotificationMessageVO $message, NotificationRouteVO $route): string
    {
        $metadataTitle = $route->getMetadata()?->get(self::TITLE_KEY);

        if (is_string($metadataTitle) && $metadataTitle !== '') {
            return $metadataTitle;
        }

        $subject = $message->getSubjectValue();

        return is_string($subject) && $subject !== '' ? $subject : self::DEFAULT_TITLE;
    }

    /**
     * @return array<string, string>
     */
    private function resolveData(NotificationMessageVO $message, NotificationRouteVO $route): array
    {
        $payload = [];

        $routeData = $route->getMetadata()?->get(self::DATA_KEY);

        // Accept both array and StrictDataObject
        if ($routeData instanceof StrictDataObject) {
            $routeData = $routeData->toArray();
        }

        if (is_array($routeData) && $routeData !== []) {
            $payload = array_merge($payload, $routeData);
        }

        $messageData = $message->getData()?->toArray() ?? [];
        if ($messageData !== []) {
            $payload = array_merge($payload, $messageData);
        }

        if ($payload === []) {
            return [];
        }

        $normalized = [];
        foreach ($payload as $key => $value) {
            $normalized[(string) $key] = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $normalized;
    }

    private function accessToken(): string
    {
        if (is_string($this->accessToken) && $this->accessToken !== '') {
            return $this->accessToken;
        }

        try {
            $client = new GoogleClient;
            $client->setAuthConfig((string) $this->config->credentials_path);
            $client->addScope($this->config->scope);

            $token = $client->fetchAccessTokenWithAssertion();
        } catch (GoogleException $exception) {
            throw new RuntimeException(
                sprintf('Firebase authentication failed: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }

        if (! isset($token['access_token']) || ! is_string($token['access_token'])) {
            throw new RuntimeException('Firebase authentication returned no access token.');
        }

        $this->accessToken = $token['access_token'];

        return $this->accessToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCredentials(): array
    {
        $path = (string) $this->config->credentials_path;

        if (! is_file($path)) {
            throw new RuntimeException(sprintf('Firebase credentials file not found at "%s".', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read Firebase credentials at "%s".', $path));
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Firebase credentials file is not valid JSON.');
        }

        return $decoded;
    }
}
