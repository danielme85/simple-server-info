<?php

declare(strict_types=1);

namespace danielme85\Server;

use danielme85\Server\Collectors\CpuCollector;
use danielme85\Server\Collectors\DiskCollector;
use danielme85\Server\Collectors\GpuCollector;
use danielme85\Server\Collectors\MemoryCollector;
use danielme85\Server\Collectors\NetworkCollector;
use danielme85\Server\Collectors\ProcessCollector;
use danielme85\Server\Collectors\SystemCollector;

/**
 * Facade providing a unified API for reading server/system information from
 * the Linux /proc and /sys virtual filesystems.
 *
 * Each feature area is delegated to a dedicated Collector class. You can
 * also consume the collectors directly for a more focused interface.
 *
 * Sources & references:
 * - https://en.wikipedia.org/wiki/Procfs
 * - https://en.wikipedia.org/wiki/Load_(computing)
 * - https://www.systutorials.com/docs/linux/man/5-proc/
 */

class Info
{
    private ProcReader      $proc;
    private SysReader       $sys;
    private SystemCollector  $system;
    private CpuCollector     $cpu;
    private MemoryCollector  $memory;
    private DiskCollector    $disk;
    private NetworkCollector $network;
    private ProcessCollector $process;
    private GpuCollector     $gpu;

    /**
     * @param string[]|null $filesystemTypes File-system types to include in volume info.
     *                                       Defaults to common types when null.
     */
    public function __construct(?array $filesystemTypes = null)
    {
        $this->proc    = new ProcReader();
        $this->sys     = new SysReader();
        $this->system  = new SystemCollector($this->proc);
        $this->cpu     = new CpuCollector($this->proc);
        $this->memory  = new MemoryCollector($this->proc);
        $this->disk    = new DiskCollector($this->proc, $filesystemTypes ?? []);
        $this->network = new NetworkCollector($this->proc);
        $this->process = new ProcessCollector($this->proc, $this->cpu);
        $this->gpu     = new GpuCollector($this->proc, $this->sys);
    }

    /**
     * Static factory — convenience shortcut for method chaining.
     *
     * @param string[]|null $filesystemTypes
     */
    public static function get(?array $filesystemTypes = null): self
    {
        return new self($filesystemTypes);
    }

    // -------------------------------------------------------------------------
    // Collectors (for direct, typed access)
    // -------------------------------------------------------------------------

    public function cpu(): CpuCollector         { return $this->cpu; }
    public function memory(): MemoryCollector   { return $this->memory; }
    public function disk(): DiskCollector       { return $this->disk; }
    public function network(): NetworkCollector { return $this->network; }
    public function processCollector(): ProcessCollector { return $this->process; }
    public function system(): SystemCollector   { return $this->system; }
    public function gpu(): GpuCollector         { return $this->gpu; }

    // -------------------------------------------------------------------------
    // Backward-compatible public API
    // -------------------------------------------------------------------------

    /** @return string[] */
    public function fileSystemTypes(): array
    {
        return $this->disk->filesystemTypes();
    }

    /** @param bool $formatted Include human-readable strings. */
    public function uptime(bool $formatted = true): array
    {
        return $this->system->uptime($formatted);
    }

    public function otherInfo(): array
    {
        return $this->system->otherInfo();
    }

    /**
     * @param int|null      $core       Return only this core (0-indexed).
     * @param string[]|null $returnonly Fields to include.
     */
    public function cpuInfo(?int $core = null, ?array $returnonly = null): array
    {
        return $this->cpu->info($core, $returnonly);
    }

    /**
     * @param int      $sampleSec Duration between samples.
     * @param int|null $rounding  Decimal places for the load percentage.
     */
    public function cpuLoad(int $sampleSec = 1, ?int $rounding = null): array
    {
        return $this->cpu->load($sampleSec, $rounding);
    }

    /** @param int $rounding Decimal places. */
    public function memoryLoad(int $rounding = 2): array
    {
        return $this->memory->load($rounding);
    }

    /** @param bool $formatSizes Return formatted strings or raw bytes. */
    public function memoryUsage(bool $formatSizes = true): array
    {
        return $this->memory->usage($formatSizes);
    }

    public function memoryInfo(): array
    {
        return $this->memory->raw();
    }

    public function diskInfo(): array
    {
        return $this->disk->diskInfo();
    }

    public function volumesInfo(): array
    {
        return $this->disk->volumesInfo();
    }

    /**
     * @param int           $pid        Process ID.
     * @param string[]|null $returnonly Fields to include.
     * @param string|null   $returntype 'stat', 'status', or null (both).
     */
    public function process(int $pid, ?array $returnonly = null, ?string $returntype = null): array
    {
        return $this->process->process($pid, $returnonly, $returntype);
    }

    /**
     * @param string[]|null $returnonly  Fields to include.
     * @param string|null   $returntype  'stat', 'status', or null (both).
     * @param bool          $runningonly Only include running processes.
     */
    public function processes(?array $returnonly = null, ?string $returntype = null, bool $runningonly = false): array
    {
        return $this->process->processes($returnonly, $returntype, $runningonly);
    }

    /**
     * @param string[]|null $returnonly Fields to include.
     * @param string|null   $returntype 'stat', 'status', or null (both).
     */
    public function processesActiveOrRunning(?array $returnonly = null, ?string $returntype = null): array
    {
        return $this->process->activeOrRunning($returnonly, $returntype);
    }

    /** @param string[]|null $returnOnly Columns to include. */
    public function networks(?array $returnOnly = null): array
    {
        return $this->network->interfaces($returnOnly);
    }

    public function tcpConnections(bool $includeLocalhost = false): array
    {
        return $this->network->tcpConnections($includeLocalhost);
    }

    public function tcpConnectionsSummarized(bool $includeLocalhost = false): array
    {
        return $this->network->tcpConnectionsSummarized($includeLocalhost);
    }

    /**
     * Return info and resource usage for all detected GPUs.
     * Keys are card names (e.g. 'card0'). Available fields depend on driver.
     */
    public function gpuInfo(): array
    {
        return $this->gpu->gpus();
    }

    // -------------------------------------------------------------------------
    // Static helpers (kept for backward compatibility)
    // -------------------------------------------------------------------------

    public static function formatBytes(int|float $size, int $precision = 2): string|int|float
    {
        return Formatter::bytes($size, $precision);
    }
}
