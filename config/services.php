<?php

return [
    'radius' => [
        'host' => env('RADIUS_HOST', 'radius'),
        'shared_secret' => env('RADIUS_SHARED_SECRET', ''),
    ],
];
