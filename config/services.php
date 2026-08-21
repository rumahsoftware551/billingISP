<?php

return [
    'radius' => [
        'host' => env('RADIUS_HOST', 'radius'),
        'shared_secret' => (is_readable(env('RADIUS_SHARED_SECRET_FILE', '/run/secrets/radius_shared_secret')) ? trim(file_get_contents(env('RADIUS_SHARED_SECRET_FILE', '/run/secrets/radius_shared_secret'))) : env('RADIUS_SHARED_SECRET', '')),
    ],
];
