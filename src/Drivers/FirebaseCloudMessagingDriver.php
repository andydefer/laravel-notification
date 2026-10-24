<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Drivers;

use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\FirebaseConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Google\Client as GoogleClient;
use Google\Exception as GoogleException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FirebaseCloudMessagingDriver extends AbstractDriver
{
    private const DEFAULT_TITLE = 'Notification';

    // ⬇️ Clés visuelles Android (vont dans `android.notification`)
    private const ICON_KEY = 'icon';

    private const IMAGE_KEY = 'image';

    private const CHANNEL_ID_KEY = 'channel_id';

    private const SOUND_KEY = 'sound';

    private const TAG_KEY = 'tag';

    private const CLICK_ACTION_KEY = 'click_action';

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
            && is_file($this->getFirebaseCredentialsFilePath());
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

        $payload = $this->resolveData($message);

        $data = [
            'type' => $message->getType(),
            'payload' => $payload === []
                ? '{}'
                : json_encode($payload, JSON_THROW_ON_ERROR),
        ];

        // ⬇️ `notification` = UNIQUEMENT title + body (champs génériques FCM)
        $notification = [
            'title' => $this->resolveTitle($message),
            'body' => $message->getBodyValue(),
        ];

        // ⬇️ `android.notification` = champs visuels spécifiques Android
        $androidNotification = $this->resolveAndroidNotification($message);

        $fcmPayload = [
            'message' => array_filter([
                'token' => $deviceToken,
                'notification' => $notification,
                'android' => $androidNotification === []
                    ? null
                    : ['notification' => $androidNotification],
                'data' => $data,
            ], static fn ($value) => $value !== null),
        ];

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout($this->config->timeout)
            ->post(
                "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                $fcmPayload,
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

    private function resolveTitle(NotificationMessageVO $message): string
    {
        $subject = $message->getSubjectValue();

        return is_string($subject) && $subject !== '' ? $subject : self::DEFAULT_TITLE;
    }

    /**
     * Construit le bloc `android.notification` de FCM HTTP v1.
     *
     * ⚠️ Les champs `icon`, `channel_id`, `sound`, `tag`, `click_action`
     * ne sont PAS acceptés dans `message.notification` (champs génériques).
     * Ils doivent aller dans `message.android.notification`.
     *
     * @return array<string, string>
     */
    private function resolveAndroidNotification(NotificationMessageVO $message): array
    {
        $metadata = $message->getMetadata();

        if ($metadata === null) {
            return [];
        }

        $android = [];

        $icon = $metadata->get(self::ICON_KEY);
        if (is_string($icon) && $icon !== '') {
            $android['icon'] = $icon;
        }

        $image = $metadata->get(self::IMAGE_KEY);
        if (is_string($image) && $image !== '') {
            $android['image'] = $image;
        }

        $channelId = $metadata->get(self::CHANNEL_ID_KEY);
        if (is_string($channelId) && $channelId !== '') {
            $android['channel_id'] = $channelId;
        }

        $sound = $metadata->get(self::SOUND_KEY);
        if (is_string($sound) && $sound !== '') {
            $android['sound'] = $sound;
        }

        $tag = $metadata->get(self::TAG_KEY);
        if (is_string($tag) && $tag !== '') {
            $android['tag'] = $tag;
        }

        $clickAction = $metadata->get(self::CLICK_ACTION_KEY);
        if (is_string($clickAction) && $clickAction !== '') {
            $android['click_action'] = $clickAction;
        }

        return $android;
    }

    /**
     * Construit `data.payload` depuis `data` du message (payload métier).
     * Le driver fait `json_encode()` UNE SEULE FOIS sur les valeurs
     * non scalaires. Ne JAMAIS pré-encoder côté appelant.
     *
     * @return array<string, string>
     */
    private function resolveData(NotificationMessageVO $message): array
    {
        $payload = $message->getData()->toArray();

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

            $client->setAuthConfig($this->getFirebaseCredentialsFilePath());
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

    public function getFirebaseCredentialsFilePath(): string
    {
        $credentialsPath = ltrim((string) $this->config->credentials_path, '/');

        if ($credentialsPath === '') {
            throw new RuntimeException('Firebase credentials path is not configured.');
        }

        return Paths::projectRoot().'/'.$credentialsPath;
    }
}
