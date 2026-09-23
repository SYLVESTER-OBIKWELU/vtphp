<?php

declare(strict_types=1);

namespace VtPhp\Session;

/**
 * Persists session payloads as serialized files under storage/framework/sessions.
 *
 * Uses serialize()/unserialize() with `allowed_classes: false` so a tampered
 * or corrupted session file can never be deserialized into arbitrary objects
 * (protects against PHP object injection).
 */
final class FileSessionStore implements SessionStoreInterface
{
    public function __construct(
        private readonly string $directory,
        private readonly int $lifetimeMinutes,
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0777, true);
        }
    }

    public function read(string $id): array
    {
        $path = $this->path($id);

        if (!is_file($path)) {
            return [];
        }

        $modifiedAt = filemtime($path);

        if ($modifiedAt !== false && $modifiedAt < time() - ($this->lifetimeMinutes * 60)) {
            @unlink($path);

            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return [];
        }

        $data = @unserialize($contents, ['allowed_classes' => false]);

        return is_array($data) ? $data : [];
    }

    public function write(string $id, array $data): void
    {
        file_put_contents($this->path($id), serialize($data), LOCK_EX);
    }

    public function destroy(string $id): void
    {
        $path = $this->path($id);

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function path(string $id): string
    {
        return $this->directory.\DIRECTORY_SEPARATOR.$id.'.sess';
    }
}
