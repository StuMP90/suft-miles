<?php

declare(strict_types=1);

namespace Surf4Miles\Http;

/**
 * A request-scoped temp file with a collision-proof random name.
 * Deleted automatically when the object is destroyed (or via delete()).
 */
final class TempFile
{
    public readonly string $path;
    private bool $deleted = false;

    public function __construct(string $suffix = '')
    {
        $dir = sys_get_temp_dir();
        $name = 'surf4miles_' . bin2hex(random_bytes(16)) . $suffix;
        $this->path = $dir . DIRECTORY_SEPARATOR . $name;
    }

    public function writeBytes(string $bytes): void
    {
        if (file_put_contents($this->path, $bytes, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to write temp file');
        }
        chmod($this->path, 0600);
    }

    public function delete(): void
    {
        if (!$this->deleted && is_file($this->path)) {
            @unlink($this->path);
        }
        $this->deleted = true;
    }

    public function __destruct()
    {
        $this->delete();
    }
}
