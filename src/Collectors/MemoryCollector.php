<?php

declare(strict_types=1);

namespace danielme85\Server\Collectors;

use danielme85\Server\Formatter;

/**
 * Reads memory and swap information from /proc/meminfo.
 */
class MemoryCollector extends AbstractCollector
{
    public function all(): array
    {
        return $this->raw();
    }

    /**
     * Return all /proc/meminfo values as an associative array (bytes).
     *
     * @return array<string, int>
     */
    public function raw(): array
    {
        $lines   = $this->proc->lines('meminfo');
        $results = [];

        foreach ($lines as $row) {
            if ($row === '') {
                continue;
            }
            $pos     = strpos($row, ':');
            $key     = trim(substr($row, 0, $pos));
            // Values are in kB (kibibytes); multiply by 1024 to get bytes
            $value   = (int) preg_replace('/\D/', '', substr($row, $pos)) * 1024;
            $results[$key] = $value;
        }

        return $results;
    }

    /**
     * Return a human-friendly usage summary.
     *
     * @param bool $formatSizes When true, return formatted strings (e.g. "512 MB").
     *                          When false, return raw byte counts.
     */
    public function usage(bool $formatSizes = true): array
    {
        $m = $this->raw();
        if (empty($m)) {
            return [];
        }

        $keys = [
            'total'      => $m['MemTotal'],
            'free'       => $m['MemFree'],
            'available'  => $m['MemAvailable'],
            'used'       => $m['MemTotal'] - $m['MemAvailable'],
            'cached'     => $m['Cached'],
            'active'     => $m['Active'],
            'inactive'   => $m['Inactive'],
            'swap_total' => $m['SwapTotal'],
            'swap_free'  => $m['SwapFree'],
        ];

        if (!$formatSizes) {
            return $keys;
        }

        return array_map([Formatter::class, 'bytes'], $keys);
    }

    /**
     * Return percentage load for RAM and swap.
     *
     * @param int $rounding Decimal places.
     */
    public function load(int $rounding = 2): array
    {
        $m = $this->raw();

        $total     = $m['MemTotal']  ?? 0;
        $available = $m['MemAvailable'] ?? 0;
        $used      = $total - $available;

        $swapTotal = $m['SwapTotal'] ?? 0;
        $swapFree  = $m['SwapFree']  ?? 0;
        $swapUsed  = $swapTotal - $swapFree;

        return [
            'load'      => round($total > 0 ? ($used / $total) * 100 : 0.0, $rounding),
            'swap_load' => round($swapTotal > 0 ? ($swapUsed / $swapTotal) * 100 : 0.0, $rounding),
        ];
    }
}
