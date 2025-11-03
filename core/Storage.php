<?php

namespace Core;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Visibility;

class Storage
{
    protected static $disks = [];
    protected static $defaultDisk = 'local';

    public static function disk($name = null)
    {
        $name = $name ?: self::$defaultDisk;

        if (!isset(self::$disks[$name])) {
            self::$disks[$name] = self::createDisk($name);
        }

        return self::$disks[$name];
    }

    protected static function createDisk($name)
    {
        $config = self::getConfig($name);

        switch ($config['driver']) {
            case 'local':
                $adapter = new LocalFilesystemAdapter($config['root']);
                break;
            default:
                throw new \Exception("Unsupported storage driver: {$config['driver']}");
        }

        return new Filesystem($adapter);
    }

    protected static function getConfig($disk)
    {
        $basePath = dirname(__DIR__);

        $configs = [
            'local' => [
                'driver' => 'local',
                'root' => $basePath . '/storage/app',
            ],
            'public' => [
                'driver' => 'local',
                'root' => $basePath . '/public_html/storage',
            ],
        ];

        return $configs[$disk] ?? $configs['local'];
    }

    // Proxy methods to default disk
    public static function exists($path)
    {
        return self::disk()->fileExists($path);
    }

    public static function get($path)
    {
        return self::disk()->read($path);
    }

    public static function put($path, $contents, $options = [])
    {
        $visibility = $options['visibility'] ?? Visibility::PRIVATE;
        self::disk()->write($path, $contents, ['visibility' => $visibility]);
        return true;
    }

    public static function putFile($path, $file, $options = [])
    {
        if (is_string($file)) {
            $contents = file_get_contents($file);
        } else {
            $contents = file_get_contents($file['tmp_name']);
        }

        return self::put($path, $contents, $options);
    }

    public static function delete($path)
    {
        self::disk()->delete($path);
        return true;
    }

    public static function copy($from, $to)
    {
        self::disk()->copy($from, $to);
        return true;
    }

    public static function move($from, $to)
    {
        self::disk()->move($from, $to);
        return true;
    }

    public static function size($path)
    {
        return self::disk()->fileSize($path);
    }

    public static function lastModified($path)
    {
        return self::disk()->lastModified($path);
    }

    public static function files($directory = '')
    {
        return self::disk()->listContents($directory)
            ->filter(fn($item) => $item->isFile())
            ->map(fn($item) => $item->path())
            ->toArray();
    }

    public static function allFiles($directory = '')
    {
        return self::disk()->listContents($directory, true)
            ->filter(fn($item) => $item->isFile())
            ->map(fn($item) => $item->path())
            ->toArray();
    }

    public static function directories($directory = '')
    {
        return self::disk()->listContents($directory)
            ->filter(fn($item) => $item->isDir())
            ->map(fn($item) => $item->path())
            ->toArray();
    }

    public static function makeDirectory($path)
    {
        self::disk()->createDirectory($path);
        return true;
    }

    public static function deleteDirectory($directory)
    {
        self::disk()->deleteDirectory($directory);
        return true;
    }

    public static function url($path)
    {
        return env('APP_URL', 'http://localhost') . '/storage/' . $path;
    }

    public static function download($path, $name = null)
    {
        $name = $name ?: basename($path);
        $contents = self::get($path);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($contents));

        echo $contents;
        exit;
    }
}
