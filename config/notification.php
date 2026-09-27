<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Notification Channels
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default notification channels that should be
    | used when sending notifications.
    |
    */
    'default_channels' => [
        'mail',
        'database',
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Configurations
    |--------------------------------------------------------------------------
    |
    | Each channel has its own configuration options. You can enable or disable
    | channels, and set their specific settings.
    |
    */
    'channels' => [

        /*
        |--------------------------------------------------------------------------
        | Mail Channel
        |--------------------------------------------------------------------------
        |
        | Configuration for sending email notifications.
        |
        */
        'mail' => [
            'enabled' => env('MAIL_ENABLED', true),
            'driver' => 'mail',
            'default_from' => env('MAIL_FROM_ADDRESS'),
            'default_from_name' => env('MAIL_FROM_NAME'),
            'default_to' => env('MAIL_DEFAULT_TO'),
        ],

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
        |
        | Configuration for storing notifications in the database.
        |
        */
        'database' => [
            'driver' => 'database',
            'table' => 'notifications',
        ],

        /*
        |--------------------------------------------------------------------------
        | SMS Channel
        |--------------------------------------------------------------------------
        |
        | Configuration for sending SMS notifications via Twilio.
        |
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
        |
        | Configuration for sending WhatsApp notifications via Meta API.
        |
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
        |
        | Configuration for sending Slack notifications.
        |
        */
        'slack' => [
            'enabled' => env('SLACK_ENABLED', false),
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ],

        'firebase' => [
            'enabled' => env('FIREBASE_NOTIFICATION_ENABLED', false),
            'credentials_path' => env('FIREBASE_CREDENTIALS_PATH'),
            'project_id' => env('FIREBASE_PROJECT_ID'),
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'timeout' => env('FIREBASE_TIMEOUT', 30),
        ],

        /*
        |--------------------------------------------------------------------------
        | Telegram Channel
        |--------------------------------------------------------------------------
        |
        | Configuration for sending Telegram notifications.
        |
        */
        'telegram' => [
            'enabled' => env('TELEGRAM_ENABLED', false),
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ],

        /*
        |--------------------------------------------------------------------------
        | Push Channel
        |--------------------------------------------------------------------------
        |
        | Configuration for sending push notifications via FCM or APNS.
        |
        */
        'push' => [
            'enabled' => env('PUSH_ENABLED', false),
            'platform' => env('PUSH_PLATFORM', 'fcm'),
            'fcm_api_key' => env('FCM_API_KEY'),
            'fcm_project_id' => env('FCM_PROJECT_ID'),
            'apns_key_path' => env('APNS_KEY_PATH'),
            'apns_key_id' => env('APNS_KEY_ID'),
            'apns_team_id' => env('APNS_TEAM_ID'),
            'apns_bundle_id' => env('APNS_BUNDLE_ID'),
            'default_sound' => env('PUSH_DEFAULT_SOUND', 'default'),
            'default_tokens' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how notification events are logged.
    |
    */
    'logging' => [
        'enabled' => env('NOTIFICATION_LOGGING_ENABLED', true),
        'channel' => env('NOTIFICATION_LOG_CHANNEL', 'daily'),
        'level' => env('NOTIFICATION_LOG_LEVEL', 'info'),
    ],
];
