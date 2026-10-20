<?php

return [
    'separate_domain' => env('CMS_SEPARATE_DOMAIN', env('APP_ENV') === 'production'),
    'site_url' => env('CMS_SITE_URL', 'https://externlangschool.ru'),
    'media_url' => env('CMS_MEDIA_URL', env('APP_ENV') === 'production' ? 'https://cw704626.tw1.ru' : null),
    'private_key' => env('CMS_PUBLISHING_KEY', storage_path('app/private/publishing-private.pem')),
];
