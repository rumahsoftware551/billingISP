<?php

return [
    'version' => env('JARINGANKU_VERSION', '1.2.0-dev'),
    'release_channel' => env('RELEASE_CHANNEL', 'development'),
    'force_https' => filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL),
    'trusted_proxies' => env('TRUSTED_PROXIES'),
    'health_token' => env('HEALTH_TOKEN'),
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 120),
    'webhook_allow_private_networks' => filter_var(env('WEBHOOK_ALLOW_PRIVATE_NETWORKS', false), FILTER_VALIDATE_BOOL),
    'webhook_allow_insecure_http' => filter_var(env('WEBHOOK_ALLOW_INSECURE_HTTP', false), FILTER_VALIDATE_BOOL),
    'webhook_user_agent' => env('WEBHOOK_USER_AGENT', 'Jaringanku-Webhook/1.0'),
    'webhook_response_body_limit' => (int) env('WEBHOOK_RESPONSE_BODY_LIMIT', 2048),
    'radius_shared_secret' => (is_readable(env('RADIUS_SHARED_SECRET_FILE', '/run/secrets/radius_shared_secret')) ? trim(file_get_contents(env('RADIUS_SHARED_SECRET_FILE', '/run/secrets/radius_shared_secret'))) : env('RADIUS_SHARED_SECRET', '')),
    'radius_client_network' => env('RADIUS_CLIENT_NETWORK', 'disabled'),
    'seed_tenant_slug' => env('SEED_TENANT_SLUG', 'demo-isp'),
    'phase08_smoke_token' => env('PHASE8_SMOKE_TOKEN', 'phase08-local-smoke'),
    'phase08_smoke_webhook_url' => env('PHASE8_SMOKE_WEBHOOK_URL', 'http://nginx/api/phase8-smoke/webhook'),
];
