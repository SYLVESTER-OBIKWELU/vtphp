<?php

declare(strict_types=1);

namespace VtPhp\Tests\Support;

final class TempDirectory
{
    public static function create(string $prefix = 'vtphp'): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix.'-'.bin2hex(random_bytes(6));
        mkdir($path, 0777, true);

        return $path;
    }

    /**
     * Best-effort recursive delete. On Windows a file can stay locked briefly
     * (e.g. a SQLite handle awaiting GC), so failures are ignored rather than
     * failing the test that owned the directory.
     */
    public static function delete(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    public static function copy(string $source, string $destination): void
    {
        @mkdir($destination, 0777, true);

        foreach (new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS) as $item) {
            $target = $destination.DIRECTORY_SEPARATOR.$item->getFilename();
            $item->isDir() ? self::copy($item->getPathname(), $target) : copy($item->getPathname(), $target);
        }
    }
}
