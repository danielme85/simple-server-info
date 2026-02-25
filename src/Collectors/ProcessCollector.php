<?php

declare(strict_types=1);

namespace danielme85\Server\Collectors;

use danielme85\Server\ProcReader;

/**
 * Reads process information from /proc/{pid}/stat and /proc/{pid}/status.
 */
class ProcessCollector extends AbstractCollector
{
    private const STAT_HEADERS = [
        'pid', 'comm', 'state', 'ppid', 'pgrp', 'session', 'tty_nr', 'tpgid',
        'flags', 'minflt', 'cminflt', 'majflt', 'cmajflt', 'utime', 'stime',
        'cutime', 'cstime', 'priority', 'nice', 'num_threads', 'itrealvalue',
        'starttime', 'vsize', 'rss', 'rsslim', 'startcode', 'endcode',
        'startstack', 'kstkesp', 'kstkeip', 'signal', 'blocked', 'sigignore',
        'sigcatch', 'wchan', 'nswap', 'cnswap', 'exit_signal', 'processor',
        'rt_priority', 'policy', 'delayacct_blkio_ticks', 'guest_time', 'cguest_time',
    ];

    /** @var CpuCollector */
    private CpuCollector $cpu;

    public function __construct(ProcReader $proc, CpuCollector $cpu)
    {
        parent::__construct($proc);
        $this->cpu = $cpu;
    }

    public function all(): array
    {
        return $this->processes();
    }

    /**
     * Return all processes.
     *
     * @param string[]|null $returnOnly Fields to include; null = all.
     * @param string|null   $type       'stat', 'status', or null (both).
     * @param bool          $runningOnly Only return running processes.
     */
    public function processes(?array $returnOnly = null, ?string $type = null, bool $runningOnly = false): array
    {
        $results = [];

        foreach ($this->proc->pidList() as $pid) {
            $entry = $this->buildProcessEntry($pid, $returnOnly, $type, $runningOnly);
            if (!empty($entry)) {
                $results[$pid] = $entry;
            }
        }

        if ($returnOnly === null || in_array('cpu_usage', $returnOnly, true)) {
            $usage = $this->processesCpuUsage($runningOnly);
            foreach ($usage as $pid => $u) {
                $results[$pid]['cpu_usage'] = $u;
            }
        }

        return $results;
    }

    /**
     * Return data for a single process.
     *
     * @param string[]|null $returnOnly Fields to include; null = all.
     * @param string|null   $type       'stat', 'status', or null (both).
     */
    public function process(int $pid, ?array $returnOnly = null, ?string $type = null): array
    {
        return $this->buildProcessEntry($pid, $returnOnly, $type, false);
    }

    /**
     * Return processes that are actively running or consuming CPU.
     *
     * @param string[]|null $returnOnly Fields to include; null = all.
     * @param string|null   $type       'stat', 'status', or null (both).
     */
    public function activeOrRunning(?array $returnOnly = null, ?string $type = null): array
    {
        // Ensure we always have enough data to filter
        if (is_array($returnOnly)) {
            foreach (['cpu_usage', 'state'] as $required) {
                if (!in_array($required, $returnOnly, true)) {
                    $returnOnly[] = $required;
                }
            }
        }

        $all     = $this->processes($returnOnly, $type, false);
        $results = [];

        if ($type === null) {
            // Results are nested: ['stat' => [...], 'status' => [...]]
            foreach ($all as $pid => $entry) {
                // Try to pull state/cpu_usage from whichever sub-array has them
                $flat = array_merge($entry['stat'] ?? [], $entry['status'] ?? []);
                if ($this->isActiveOrRunning($flat)) {
                    $results[$pid] = $entry;
                }
            }
        } else {
            foreach ($all as $pid => $entry) {
                if ($this->isActiveOrRunning($entry)) {
                    $results[$pid] = $entry;
                }
            }
        }

        return $results;
    }

    /**
     * Return per-process CPU usage percentages (requires a 1-second sample).
     */
    public function processesCpuUsage(bool $runningOnly = false): array
    {
        $fields = ['pid', 'utime', 'stime', 'processor'];

        $first1     = $this->processes($fields, 'stat', $runningOnly);
        $totalFirst = $this->cpu->stat();
        sleep(1);
        $first2      = $this->processes($fields, 'stat', $runningOnly);
        $totalSecond = $this->cpu->stat();

        $results = [];
        foreach ($first1 as $pid => $row) {
            if (!isset($first2[$pid])) {
                continue;
            }
            $results[(string) $pid] = $this->calculateProcessCpu(
                $row,
                $first2[$pid],
                $totalFirst,
                $totalSecond
            );
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function buildProcessEntry(int $pid, ?array $returnOnly, ?string $type, bool $runningOnly): array
    {
        return match ($type) {
            'stat'   => $this->readStat($pid, $returnOnly, $runningOnly),
            'status' => $this->readStatus($pid, $returnOnly, $runningOnly),
            default  => [
                'stat'   => $this->readStat($pid, $returnOnly, $runningOnly),
                'status' => $this->readStatus($pid, $returnOnly, $runningOnly),
            ],
        };
    }

    private function readStatus(int $pid, ?array $returnOnly, bool $runningOnly): array
    {
        if ($runningOnly && is_array($returnOnly) && !in_array('state', $returnOnly, true)) {
            $returnOnly[] = 'state';
        }

        $lines   = $this->proc->lines("$pid/status");
        $results = $this->parseKeyValue($lines);

        if (empty($results)) {
            return [];
        }

        if ($runningOnly && ($results['state'] ?? '') !== 'R (running)') {
            return [];
        }

        return $this->filterKeys($results, $returnOnly);
    }

    private function readStat(int $pid, ?array $returnOnly, bool $runningOnly): array
    {
        if ($runningOnly && is_array($returnOnly) && !in_array('state', $returnOnly, true)) {
            $returnOnly[] = 'state';
        }

        $lines = $this->proc->lines("$pid/stat");
        if (empty($lines[0])) {
            return [];
        }

        // The comm field (index 1) is wrapped in parentheses and may contain spaces.
        // Find the last ')' to correctly split the remainder of the line.
        $raw       = $lines[0];
        $commStart = strpos($raw, '(');
        $commEnd   = strrpos($raw, ')');
        $pidPart   = substr($raw, 0, $commStart - 1);
        $comm      = substr($raw, $commStart + 1, $commEnd - $commStart - 1);
        $rest      = explode(' ', trim(substr($raw, $commEnd + 2)));
        $parts     = array_merge([$pidPart, $comm], $rest);

        if ($runningOnly && ($parts[2] ?? '') !== 'R') {
            return [];
        }

        $results = [];
        foreach (self::STAT_HEADERS as $i => $header) {
            if (empty($returnOnly) || in_array($header, $returnOnly, true)) {
                $results[$header] = $parts[$i] ?? null;
            }
        }

        return $results;
    }

    private function isActiveOrRunning(array $data): bool
    {
        $cpuUsage = $data['cpu_usage'] ?? 0;
        $state    = $data['state']     ?? '';

        return $cpuUsage > 0 || $state === 'R' || $state === 'R (running)';
    }

    private function calculateProcessCpu(array $row1, array $row2, array $total1, array $total2): float
    {
        $time1 = (int) $row1['utime'] + (int) $row1['stime'];
        $time2 = (int) $row2['utime'] + (int) $row2['stime'];

        $cpuId      = "cpu{$row1['processor']}";
        $cpuTotal1  = $total1['cpu'][$cpuId] ?? [];
        $cpuTotal2  = $total2['cpu'][$cpuId] ?? [];

        $sumTotal1 = (int) array_sum($cpuTotal1);
        $sumTotal2 = (int) array_sum($cpuTotal2);

        if ($sumTotal1 <= 0 || $sumTotal2 <= 0) {
            return 0.0;
        }

        return round(100 * ($time2 - $time1) / ($sumTotal2 - $sumTotal1), 2);
    }
}
