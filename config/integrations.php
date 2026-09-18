<?php

return [
    'mode' => env('INTEGRATION_MODE', 'sandbox'),

    'satusehat' => [
        'fake' => env('SATUSEHAT_FAKE', true),
        'base_url' => env('SATUSEHAT_BASE_URL', 'https://api-satusehat-stg.dto.kemkes.go.id'),
        'token_url' => env('SATUSEHAT_TOKEN_URL', 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken'),
    ],

    'bpjs' => [
        'fake' => env('BPJS_FAKE', true),
        'base_url' => env('BPJS_BASE_URL', 'https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-rest-dev'),
    ],
];
