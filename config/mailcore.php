<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MailCore Domain Configuration
    |--------------------------------------------------------------------------
    |
    | Define the primary domain and hostname for your mail server.
    |
    */

    'domain' => env('MAILCORE_DOMAIN', 'example.com'),
    'hostname' => env('MAILCORE_HOSTNAME', 'mail.example.com'),
    'ip' => env('MAILCORE_IP', '0.0.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Postfix & Mail Server Paths
    |--------------------------------------------------------------------------
    |
    | Configure the paths for mail server components.
    |
    */

    'postfix_log' => env('MAILCORE_POSTFIX_LOG', '/var/log/mail.log'),
    'dkim_path' => env('MAILCORE_DKIM_PATH', '/etc/opendkim/keys'),
    'dovecot_virtual_users' => env('MAILCORE_DOVECOT_VIRTUAL_USERS', '/etc/dovecot/virtual-users'),

    /*
    |--------------------------------------------------------------------------
    | Mailbox Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default mailbox settings.
    |
    */

    'max_quota_mb' => env('MAILCORE_MAX_QUOTA_MB', 5120),
    'default_quota_mb' => 1024,

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */

    'api_token_length' => env('MAILCORE_API_TOKEN_LENGTH', 64),
    'enable_2fa' => env('MAILCORE_ENABLE_2FA', true),
    'session_timeout' => env('MAILCORE_SESSION_TIMEOUT', 3600),

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Logging
    |--------------------------------------------------------------------------
    */

    'log_parser' => [
        'enabled' => env('MAILCORE_LOG_PARSER_ENABLED', true),
        'interval' => env('MAILCORE_LOG_PARSER_INTERVAL', 300), // seconds
    ],

    'bounce_detection' => [
        'enabled' => env('MAILCORE_BOUNCE_DETECTION_ENABLED', true),
    ],

    'blacklist_check' => [
        'enabled' => env('MAILCORE_BLACKLIST_CHECK_ENABLED', true),
        'providers' => [
            'spamhaus' => 'zen.spamhaus.org',
            'spamcop' => 'bl.spamcop.net',
            'barracuda' => 'b.barracudacentral.org',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */

    'features' => [
        'sandbox_mode' => env('MAILCORE_SANDBOX_MODE', false),
        'telegram_notifications' => env('MAILCORE_TELEGRAM_NOTIFICATIONS', false),
        'dmarc_reporting' => true,
        'spf_validation' => true,
        'dkim_validation' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Notifications
    |--------------------------------------------------------------------------
    */

    'telegram' => [
        'bot_token' => env('MAILCORE_TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('MAILCORE_TELEGRAM_CHAT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DNS Validators
    |--------------------------------------------------------------------------
    */

    'dns_validators' => [
        'spf' => [
            'required_records' => [
                'v=spf1',
                'a',
                'mx',
                '-all',
            ],
        ],
        'dmarc' => [
            'required_records' => [
                'v=DMARC1',
                'p=',
            ],
        ],
    ],

];
