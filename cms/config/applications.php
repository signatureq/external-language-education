<?php

return [
    'enabled' => (bool) env('APPLICATIONS_ENABLED', false),
    'recipient' => env('APPLICATIONS_RECIPIENT'),
    'endpoint' => env('APPLICATIONS_ENDPOINT'),
    'origins' => array_filter(explode(',', env('APPLICATIONS_ORIGINS', 'https://externlangschool.ru'))),
];
