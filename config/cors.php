<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:3000', 'https://clozy.qualixe.com'],

    // Also allow Vercel's preview-deployment URLs (unique per branch/PR),
    // not just the production domain above.
    'allowed_origins_patterns' => ['#^https://.*\.vercel\.app$#'],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
