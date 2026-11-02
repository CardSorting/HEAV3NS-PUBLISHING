<?php

return [
    // New Fans releases opt in to the BS5 variable contract. Legacy clients omit it.
    'generator_url' => env('THEME_GENERATOR_URL', 'https://themes-v2.qdev.tech'),
    'generator_require_https' => env('THEME_GENERATOR_REQUIRE_HTTPS', true),
    'product' => env('THEME_PRODUCT', 'fans'),
    'format' => 'bootstrap5-vars',
    'stylesheet' => 'css/theme/theme.css',

    'default_colors' => [
        'theme_color_code' => '#cb0c9f',
        'theme_gradient_from' => '#7928CA',
        'theme_gradient_to' => '#FF0080',
    ],
];
