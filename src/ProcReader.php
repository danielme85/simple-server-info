<?php

declare(strict_types=1);

namespace danielme85\Server;

/**
 * Reads raw text data from the /proc virtual filesystem.
 *
 * Centralizes all I/O so collectors contain only parsing logic.
 */
class ProcReader
{
    public function __construct(private readonly string $basePath = '/proc') {}

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Read a /proc file and return its lines as an array.
     *
     * @return string[]
     */
    public function lines(string $path): array
    {
        $full = "$this->basePath/$path";

        if (!is_file($full) || !is_readable($full)) {
            return [];
        }

        $contents = @file_get_contents($full);

        return $contents !== false && $contents !== ''
            ? explode(PHP_EOL, $contents)
            : [];
    }

    /**
     * Parse a columnar /proc file into an array-of-arrays keyed by header row.
     *
     * @return array<int, array<string, string>>
     */
    public function parseColumnar(string $path): array
    {
        $raw = $this->lines($path);
        $results = [];
        $headers = [];
        $first = true;

        foreach ($raw as $row) {
            if ($row === '') {
                continue;
            }

            $values = preg_split('/\s+/', trim($row));

            if ($first) {
                $headers = $values;
                $first = false;
                continue;
            }

            $entry = [];
            foreach ($headers as $i => $header) {
                $entry[$header] = $values[$i] ?? '';
            }
            $results[] = $entry;
        }

        return $results;
    }

    /**
     * Scan the base /proc directory and return numeric (PID) entries.
     *
     * @return int[]
     */
    public function pidList(): array
    {
        $scan = scandir($this->basePath) ?: [];
        $pids = array_filter($scan, 'is_numeric');
        $pids = array_map('intval', $pids);
        sort($pids);

        return $pids;
    }
}
