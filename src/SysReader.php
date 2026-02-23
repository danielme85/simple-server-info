<?php

declare(strict_types=1);

namespace danielme85\Server;

/**
 * Reads raw data from the /sys virtual filesystem (sysfs).
 *
 * Centralizes all sysfs I/O so collectors contain only parsing logic.
 * Mirrors the interface of ProcReader but targets single-value sysfs files
 * and provides directory listing for discovering hardware entries.
 */
class SysReader
{
    public function __construct(private readonly string $basePath = '/sys') {}

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Read a single sysfs attribute file and return its trimmed value.
     * Returns null if the file does not exist or is unreadable.
     */
    public function read(string $path): ?string
    {
        $full = "$this->basePath/$path";

        if (!is_file($full) || !is_readable($full)) {
            return null;
        }

        $contents = @file_get_contents($full);

        return $contents !== false ? trim($contents) : null;
    }

    /**
     * Read a sysfs attribute file and return its value as an integer.
     * Returns null if the file does not exist or is unreadable.
     */
    public function readInt(string $path): ?int
    {
        $value = $this->read($path);

        return $value !== null ? (int) $value : null;
    }

    /**
     * Read a sysfs file and return its lines as an array.
     *
     * @return string[]
     */
    public function lines(string $path): array
    {
        $value = $this->read($path);

        return $value !== null && $value !== ''
            ? explode(PHP_EOL, $value)
            : [];
    }

    /**
     * List entries in a sysfs directory, excluding '.' and '..'.
     * Returns an empty array if the directory does not exist.
     *
     * @return string[]
     */
    public function listDir(string $path): array
    {
        $full = "$this->basePath/$path";

        if (!is_dir($full)) {
            return [];
        }

        return array_values(
            array_filter(scandir($full) ?: [], fn($e) => $e !== '.' && $e !== '..')
        );
    }
}
