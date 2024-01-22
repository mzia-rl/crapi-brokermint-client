<?php

return [
    'api_key' => env('BROKERMINT_API_KEY'),
    'base_uri' => env('BROKERMINT_BASE_URI', 'https://my.brokermint.com/api/v1/'),
    'throttling' => [
        'max_wait' => env('BROKERMINT_THROTTLE_MAX_WAIT', 1000 * 10)
    ]
];