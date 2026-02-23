<?php

declare(strict_types=1);

namespace danielme85\Server;

/**
 * Stateless formatting helpers.
 */
final class Formatter
{
    private function __construct() {}

    /**
     * Format a byte count as a human-readable string (e.g. "1.50 GB").
     *
     * @param int|float $size
     * @param int       $precision
     * @return string|int|float Returns the original value unchanged when ≤ 0.
     */
    public static function bytes(int|float $size, int $precision = 2): string|int|float
    {
        if ($size <= 0) {
            return $size;
        }

        $suffixes = [' bytes', ' KB', ' MB', ' GB', ' TB'];
        $base     = (int) floor(log((int) $size, 1024));
        $base     = min($base, count($suffixes) - 1);

        return round($size / (1024 ** $base), $precision) . $suffixes[$base];
    }
}
