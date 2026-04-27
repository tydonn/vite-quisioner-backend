<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'web_a_sso' => [
        'secret' => env('WEB_A_SSO_SECRET', ''),
        'issuer' => env('WEB_A_SSO_ISSUER', ''),
        'audience' => env('WEB_A_SSO_AUDIENCE', ''),
        'leeway' => (int) env('WEB_A_SSO_LEEWAY', 5),
        'timestamp_tolerance' => (int) env('WEB_A_SSO_TIMESTAMP_TOLERANCE', 60),
        'nonce_ttl' => (int) env('WEB_A_SSO_NONCE_TTL', 300),
        'code_ttl' => (int) env('WEB_A_SSO_CODE_TTL', 60),
    ],

];
