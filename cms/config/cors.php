<?php

return [
    'paths' => ['api/applications', 'api/applications/token'],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_origins' => array_filter(explode(',', env('APPLICATIONS_ORIGINS', 'https://externlangschool.ru'))),
    'allowed_headers' => ['Accept', 'Content-Type'],
    'exposed_headers' => ['Retry-After'],
    'max_age' => 600,
    'supports_credentials' => false,
];
