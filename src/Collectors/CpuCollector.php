<?php

declare(strict_types=1);

namespace danielme85\Server\Collectors;

/**
 * Reads CPU hardware info and calculates load from /proc/cpuinfo and /proc/stat.
 */
class CpuCollector extends AbstractCollector
{
    public function all(): array
    {
        return [
            'info' => $this->info(),
            'load' => $this->load(),
        ];
    }

    /**
     * Return per-core CPU info, optionally limited to a single core and/or
     * a subset of fields.
     *
     * @param int|null      $core       Return only this core (0-indexed); null = all cores.
     * @param string[]|null $returnOnly Return only these fields.
     */
    public function info(?int $core = null, ?array $returnOnly = null): array
    {
        $cores = $this->parseCpuInfo($returnOnly);

        return $core !== null ? ($cores[$core] ?? []) : $cores;
    }

    /**
     * Sample /proc/stat twice and calculate percentage load per core.
     *
     * @param int      $sampleSec Duration (seconds) to wait between samples.
     * @param int|null $rounding  Decimal places for the load percentage.
     */
    public function load(int $sampleSec = 1, ?int $rounding = null): array
    {
        $sample1 = $this->parseStat();
        sleep($sampleSec);
        $sample2 = $this->parseStat();

        return $this->calculateLoad($sample1, $sample2, $rounding ?? 2);
    }

    /**
     * Return a raw /proc/stat snapshot keyed by CPU label.
     */
    public function stat(): array
    {
        return $this->parseStat();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function parseCpuInfo(?array $returnOnly): array
    {
        $lines   = $this->proc->lines('cpuinfo');
        $results = [];
        $core    = 0;

        foreach ($lines as $row) {
            if ($row === '') {
                $core++;
                continue;
            }

            $pos   = strpos($row, ':');
            $key   = $this->normaliseKey(substr($row, 0, $pos));
            $value = trim(substr($row, $pos + 1));

            if (empty($returnOnly) || in_array($key, $returnOnly, true)) {
                $results[$core][$key] = $value;
            }
        }

        return $results;
    }

    private function parseStat(): array
    {
        $lines   = $this->proc->lines('stat');
        $results = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'cpu')) {
                $parts = preg_split('/\s+/', $line);
                $label = $parts[0];

                $results['cpu'][$label] = [
                    'user'       => (int) ($parts[1] ?? 0),
                    'nice'       => (int) ($parts[2] ?? 0),
                    'system'     => (int) ($parts[3] ?? 0),
                    'idle'       => (int) ($parts[4] ?? 0),
                    'iowait'     => (int) ($parts[5] ?? 0),
                    'irq'        => (int) ($parts[6] ?? 0),
                    'softirq'    => (int) ($parts[7] ?? 0),
                    'steal'      => (int) ($parts[8] ?? 0),
                    'guest'      => (int) ($parts[9] ?? 0),
                    'guest_nice' => (int) ($parts[10] ?? 0),
                ];
            } elseif (str_starts_with($line, 'ctxt')) {
                $results['ctxt'] = (int) preg_replace('/\D/', '', $line);
            } elseif (str_starts_with($line, 'btime')) {
                $results['btime'] = (int) preg_replace('/\D/', '', $line);
            } elseif (str_starts_with($line, 'processes')) {
                $results['processes'] = (int) preg_replace('/\D/', '', $line);
            } elseif (str_starts_with($line, 'procs_running')) {
                $results['procs_running'] = (int) preg_replace('/\D/', '', $line);
            } elseif (str_starts_with($line, 'procs_blocked')) {
                $results['procs_blocked'] = (int) preg_replace('/\D/', '', $line);
            }
        }

        return $results;
    }

    private function calculateLoad(array $sample1, array $sample2, int $rounding): array
    {
        $results = [];
        $coreIdx = 0;
        $first   = true;

        foreach ($sample1['cpu'] ?? [] as $label => $m1) {
            $m2 = $sample2['cpu'][$label] ?? [];

            $prevIdle = $m1['idle'] + $m1['guest'] + $m1['guest_nice'];
            $lastIdle = ($m2['idle'] ?? 0) + ($m2['guest'] ?? 0) + ($m2['guest_nice'] ?? 0);

            $prevActive = $m1['user'] + $m1['nice'] + $m1['system'] + $m1['irq'] + $m1['softirq'] + $m1['steal'] + $m1['iowait'];
            $lastActive = ($m2['user'] ?? 0) + ($m2['nice'] ?? 0) + ($m2['system'] ?? 0) + ($m2['irq'] ?? 0) + ($m2['softirq'] ?? 0) + ($m2['steal'] ?? 0) + ($m2['iowait'] ?? 0);

            $prevTotal = $prevActive + $prevIdle;
            $lastTotal = $lastActive + $lastIdle;
            $totalDiff = $lastTotal - $prevTotal;
            $idleDiff  = $lastIdle - $prevIdle;

            $percentage = $totalDiff > 0
                ? ($totalDiff - $idleDiff) / $totalDiff
                : 0.0;

            $displayLabel = $first ? 'CPU' : "Core#{$coreIdx}";

            $results[$label] = [
                'label' => $displayLabel,
                'load'  => round($percentage * 100, $rounding),
            ];

            if ($first) {
                $first = false;
            } else {
                $coreIdx++;
            }
        }

        return $results;
    }
}
