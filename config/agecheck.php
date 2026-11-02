<?php

return [
    'cookie_name' => 'adult_access',
    'oauth_state_key' => 'agecheck.oauth_state',
    'return_url_key' => 'agecheck.return_url',

    'ageverif' => [
        'authorization_endpoint' => 'https://api.ageverif.com/v1/oauth2/checker',
        'token_endpoint' => 'https://api.ageverif.com/v1/oauth2/token',
        'resources_endpoint' => 'https://api.ageverif.com/v1/oauth2/resources',
        'checker_script_url' => 'https://www.ageverif.com/checker.js',
    ],
];
