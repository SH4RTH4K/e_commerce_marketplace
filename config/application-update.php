<?php

return [
    'repository_url' => env('APPLICATION_UPDATE_REPOSITORY_URL', 'https://github.com/SH4RTH4K/e_commerce_marketplace.git'),
    'git_binary' => env('APPLICATION_UPDATE_GIT_BINARY'),
    'git_binary_candidates' => [
        '/usr/bin/git',
        '/usr/local/bin/git',
        '/usr/local/cpanel/3rdparty/bin/git',
        'git',
    ],
];
