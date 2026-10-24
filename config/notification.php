<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Notification Channels
    |--------------------------------------------------------------------------
    */
    'default_channels' => [
        'mail',
        'database',
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Configurations
    |--------------------------------------------------------------------------
    */
    'channels' => [

        /*
        |--------------------------------------------------------------------------
        | Mail Channel
        |--------------------------------------------------------------------------
        */
        'mail' => [
            'enabled' => env('MAIL_ENABLED', true),
            'driver' => 'mail',
            'default_from' => env('MAIL_FROM_ADDRESS'),
            'default_from_name' => env('MAIL_FROM_NAME'),
            'default_to' => env('MAIL_DEFAULT_TO'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Pusher Channel
        |--------------------------------------------------------------------------
        */
        'pusher' => [
            'enabled' => env('PUSHER_NOTIFICATION_ENABLED', false),
            'app_id' => env('PUSHER_APP_ID'),
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
            'use_tls' => env('PUSHER_USE_TLS', true),
            'timeout' => env('PUSHER_TIMEOUT', 30),
            'default_channel' => env('PUSHER_NOTIFICATION_CHANNEL', 'notifications'),
            'user_channel_prefix' => env('PUSHER_USER_CHANNEL_PREFIX', 'private-user-'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Database Channel
        |--------------------------------------------------------------------------
        */
        'database' => [
            'driver' => 'database',
            'table' => 'notifications',
        ],

        /*
        |--------------------------------------------------------------------------
        | SMS Channel
        |--------------------------------------------------------------------------
        */
        'sms' => [
            'enabled' => env('SMS_ENABLED', false),
            'driver' => 'twilio',
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],

        /*
        |--------------------------------------------------------------------------
        | WhatsApp Channel
        |--------------------------------------------------------------------------
        */
        'whatsapp' => [
            'enabled' => env('WHATSAPP_ENABLED', false),
            'driver' => 'meta',
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Slack Channel
        |--------------------------------------------------------------------------
        */
        'slack' => [
            'enabled' => env('SLACK_ENABLED', false),
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Firebase Cloud Messaging (Android / Web / iOS via FCM)
        |--------------------------------------------------------------------------
        */
        'firebase' => [
            // Server-side (admin SDK)
            'enabled' => env('FIREBASE_NOTIFICATION_ENABLED', false),
            'credentials_path' => env('FIREBASE_CREDENTIALS_PATH'),
            'project_id' => env('FIREBASE_PROJECT_ID'),
            'scope' => env('FIREBASE_SCOPE', 'https://www.googleapis.com/auth/firebase.messaging'),
            'timeout' => env('FIREBASE_TIMEOUT', 30),

            // Client-side (public config)
            'api_key' => env('FIREBASE_API_KEY'),
            'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
            'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
            'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
            'app_id' => env('FIREBASE_APP_ID'),
            'measurement_id' => env('FIREBASE_MEASUREMENT_ID'),
            'vapid_key' => env('FIREBASE_VAPID_KEY'),
            'legacy_key' => env('FIREBASE_KEY'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Web Push Channel
        |--------------------------------------------------------------------------
        */
        'webpush' => [
            'enabled' => env('WEBPUSH_NOTIFICATION_ENABLED', false),
            'subject' => env('WEBPUSH_SUBJECT', env('APP_URL')),
            'public_key' => env('WEBPUSH_PUBLIC_KEY'),
            'private_key' => env('WEBPUSH_PRIVATE_KEY'),
            'ttl' => env('WEBPUSH_TTL', 3600),
            'urgency' => env('WEBPUSH_URGENCY', 'normal'),
            'topic' => env('WEBPUSH_TOPIC', 'notification'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Telegram Channel
        |--------------------------------------------------------------------------
        */
        'telegram' => [
            'enabled' => env('TELEGRAM_ENABLED', false),
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ],

        /*
        |--------------------------------------------------------------------------
        | APNS Channel (Apple Push Notification service)
        |--------------------------------------------------------------------------
        |
        | ⚠️ À IMPLÉMENTER : ApnsDriver est actuellement un squelette.
        |    Pour iOS, utiliser FirebaseCloudMessagingDriver (qui proxifie vers APNS)
        |    en attendant une implémentation native.
        |
        */
        'apns' => [
            'enabled' => env('APNS_NOTIFICATION_ENABLED', false),
            'key_path' => env('APNS_KEY_PATH'),
            'key_id' => env('APNS_KEY_ID'),
            'team_id' => env('APNS_TEAM_ID'),
            'bundle_id' => env('APNS_BUNDLE_ID'),
            'production' => env('APNS_PRODUCTION', true),
            'default_sound' => env('APNS_DEFAULT_SOUND', 'default'),
            'timeout' => env('APNS_TIMEOUT', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('NOTIFICATION_LOGGING_ENABLED', true),
        'channel' => env('NOTIFICATION_LOG_CHANNEL', 'daily'),
        'level' => env('NOTIFICATION_LOG_LEVEL', 'info'),
    ],
];
