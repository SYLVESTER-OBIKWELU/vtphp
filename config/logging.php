<?php

declare(strict_types=1);

// Resolve LOG_PATH against the app base path so log location doesn't depend
// on the current working directory (e.g. `php -S` uses the docroot as cwd).
$logPath = (string) env('LOG_PATH', 'storage/logs/app.log');
$logPathIsAbsolute = str_starts_with($logPath, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $logPath) === 1;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],

        'single' => [
            'driver' => 'single',
            'path' => $logPathIsAbsolute ? $logPath : base_path($logPath),
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'stderr' => [
            'driver' => 'stderr',
            'level' => env('LOG_LEVEL', 'debug'),
        ],
    ],
];
