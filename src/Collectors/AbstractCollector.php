<?php

declare(strict_types=1);

namespace danielme85\Server\Collectors;

use danielme85\Server\Contracts\CollectorInterface;
use danielme85\Server\ProcReader;

abstract class AbstractCollector implements CollectorInterface
{
    public function __construct(protected readonly ProcReader $proc) {}

    /**
     * Normalise a raw /proc key: lowercase, spaces → underscores.
     */
    protected function normaliseKey(string $key): string
    {
        return trim(strtolower(str_replace(' ', '_', $key)));
    }

    /**
     * Parse a colon-separated key:value file (e.g. /proc/meminfo, /proc/{pid}/status)
     * into a flat associative array.
     *
     * @param string[] $lines
     * @return array<string, string>
     */
    protected function parseKeyValue(array $lines, bool $normalise = true): array
    {
        $results = [];
        foreach ($lines as $row) {
            if ($row === '') {
                continue;
            }
            $pos = strpos($row, ':');
            if ($pos === false) {
                continue;
            }
            $key   = $normalise ? $this->normaliseKey(substr($row, 0, $pos)) : trim(substr($row, 0, $pos));
            $value = trim(substr($row, $pos + 1));
            $results[$key] = $value;
        }

        return $results;
    }

    /**
     * Filter an associative array to only the requested keys.
     * Returns the original array when $only is null/empty.
     *
     * @param array<string, mixed> $data
     * @param string[]|null        $only
     */
    protected function filterKeys(array $data, ?array $only): array
    {
        if (empty($only)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($only));
    }
}
