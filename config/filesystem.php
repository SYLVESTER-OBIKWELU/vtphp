<?php

declare(strict_types=1);

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        // Never web-accessible directly; reads must go through the application.
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
        ],

        // Served directly from a public URL (local /storage symlink or CDN).
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        // Any S3-compatible provider: AWS S3, Cloudflare R2, MinIO, DO Spaces.
        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
            'visibility' => 'private',
        ],

        // Media/CDN service, not a generic disk — see docs/services/filesystem.md.
        'cloudinary' => [
            'driver' => 'cloudinary',
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'api_key' => env('CLOUDINARY_API_KEY'),
            'api_secret' => env('CLOUDINARY_API_SECRET'),
            'folder' => env('CLOUDINARY_FOLDER', 'app'),
        ],
    ],
];
