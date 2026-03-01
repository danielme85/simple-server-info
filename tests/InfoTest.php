<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use danielme85\Server\Info;
use danielme85\Server\Formatter;
use danielme85\Server\Collectors\CpuCollector;
use danielme85\Server\Collectors\DiskCollector;
use danielme85\Server\Collectors\MemoryCollector;
use danielme85\Server\Collectors\NetworkCollector;
use danielme85\Server\Collectors\ProcessCollector;
use danielme85\Server\Collectors\GpuCollector;
use danielme85\Server\Collectors\SystemCollector;

final class InfoTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    /**
     * @group init
     */
    public function testDefaultFilesystemTypes(): void
    {
        $info  = new Info();
        $types = $info->fileSystemTypes();
        $this->assertNotEmpty($types);
        $this->assertContains('ext4', $types);
    }

    /**
     * @group init
     */
    public function testCustomFilesystemTypes(): void
    {
        $info  = new Info(['ext4']);
        $types = $info->fileSystemTypes();
        $this->assertContains('ext4', $types);
        $this->assertCount(1, $types);
    }

    /**
     * @group init
     */
    public function testStaticGetFactory(): void
    {
        $this->assertInstanceOf(Info::class, Info::get());
    }

    /**
     * @group init
     */
    public function testCollectorAccessors(): void
    {
        $info = new Info();
        $this->assertInstanceOf(CpuCollector::class,     $info->cpu());
        $this->assertInstanceOf(MemoryCollector::class,  $info->memory());
        $this->assertInstanceOf(DiskCollector::class,    $info->disk());
        $this->assertInstanceOf(NetworkCollector::class, $info->network());
        $this->assertInstanceOf(ProcessCollector::class, $info->processCollector());
        $this->assertInstanceOf(SystemCollector::class,  $info->system());
        $this->assertInstanceOf(GpuCollector::class,     $info->gpu());
    }

    // -------------------------------------------------------------------------
    // System / uptime
    // -------------------------------------------------------------------------

    /**
     * @group uptime
     */
    public function testUptimeFormatted(): void
    {
        $uptime = Info::get()->uptime(true);

        $this->assertNotEmpty($uptime);
        $this->assertGreaterThan(0, $uptime['started_unix']);
        $this->assertGreaterThan(0, $uptime['current_unix']);
        $this->assertGreaterThan(0, $uptime['uptime_unix']);
        $this->assertNotEmpty($uptime['uptime']);
        $this->assertNotEmpty($uptime['uptime_text']);
        $this->assertArrayHasKey('started', $uptime);
        $this->assertArrayHasKey('current', $uptime);
    }

    /**
     * @group uptime
     */
    public function testUptimeUnformatted(): void
    {
        $uptime = Info::get()->uptime(false);

        $this->assertArrayHasKey('started_unix', $uptime);
        $this->assertArrayHasKey('current_unix', $uptime);
        $this->assertArrayHasKey('uptime_unix',  $uptime);
        $this->assertArrayNotHasKey('uptime_text', $uptime);
    }

    /**
     * @group uptime
     */
    public function testOtherInfo(): void
    {
        $info = Info::get()->otherInfo();
        $this->assertArrayHasKey('version', $info);
        $this->assertNotEmpty($info['version']);
    }

    // -------------------------------------------------------------------------
    // CPU
    // -------------------------------------------------------------------------

    /**
     * @group cpu
     */
    public function testCpuInfoAll(): void
    {
        $cpuinfo = Info::get()->cpuInfo();
        $this->assertNotEmpty($cpuinfo);
        // Each element is a per-core associative array
        $firstCore = reset($cpuinfo);
        $this->assertIsArray($firstCore);
        // 'processor' is present on all architectures (x86, ARM, etc.)
        $this->assertArrayHasKey('processor', $firstCore);
    }

    /**
     * @group cpu
     */
    public function testCpuInfoSingleCore(): void
    {
        $core0 = Info::get()->cpuInfo(0);
        $this->assertNotEmpty($core0);
        $this->assertArrayHasKey('processor', $core0);
    }

    /**
     * @group cpu
     */
    public function testCpuInfoReturnOnly(): void
    {
        $result = Info::get()->cpuInfo(0, ['processor']);
        $this->assertArrayHasKey('processor', $result);
        $this->assertCount(1, $result);
    }

    /**
     * @group cpu
     */
    public function testCpuLoad(): void
    {
        exec('php ' . __DIR__ . '/../PrimeStress.php > /dev/null 2>/dev/null &');
        $cpuload = Info::get()->cpuLoad(1, 2);

        $this->assertArrayHasKey('cpu', $cpuload);
        $this->assertArrayHasKey('load',  $cpuload['cpu']);
        $this->assertArrayHasKey('label', $cpuload['cpu']);
        $this->assertGreaterThanOrEqual(0, $cpuload['cpu']['load']);
    }

    /**
     * @group cpu
     */
    public function testCpuStat(): void
    {
        $stat = Info::get()->cpu()->stat();
        $this->assertArrayHasKey('cpu', $stat);
        $this->assertArrayHasKey('cpu', $stat['cpu']);
        $aggregate = $stat['cpu']['cpu'];
        foreach (['user', 'nice', 'system', 'idle'] as $field) {
            $this->assertArrayHasKey($field, $aggregate);
        }
    }

    // -------------------------------------------------------------------------
    // Memory
    // -------------------------------------------------------------------------

    /**
     * @group memory
     */
    public function testMemoryUsageFormatted(): void
    {
        $usage = Info::get()->memoryUsage(true);
        $this->assertNotEmpty($usage);
        foreach (['total', 'free', 'available', 'used', 'swap_total', 'swap_free'] as $key) {
            $this->assertArrayHasKey($key, $usage);
        }
    }

    /**
     * @group memory
     */
    public function testMemoryUsageRaw(): void
    {
        $usage = Info::get()->memoryUsage(false);
        $this->assertNotEmpty($usage);
        $this->assertIsInt($usage['total']);
        $this->assertGreaterThan(0, $usage['total']);
    }

    /**
     * @group memory
     */
    public function testMemoryLoad(): void
    {
        $load = Info::get()->memoryLoad(2);
        $this->assertArrayHasKey('load',      $load);
        $this->assertArrayHasKey('swap_load', $load);
        $this->assertGreaterThanOrEqual(0, $load['load']);
        $this->assertLessThanOrEqual(100, $load['load']);
    }

    /**
     * @group memory
     */
    public function testMemoryInfo(): void
    {
        $raw = Info::get()->memoryInfo();
        $this->assertNotEmpty($raw);
        $this->assertArrayHasKey('MemTotal',     $raw);
        $this->assertArrayHasKey('MemAvailable', $raw);
        $this->assertGreaterThan(0, $raw['MemTotal']);
    }

    // -------------------------------------------------------------------------
    // Disk
    // -------------------------------------------------------------------------

    /**
     * @group disk
     */
    public function testDiskInfo(): void
    {
        $disks = Info::get()->diskInfo();
        $this->assertNotEmpty($disks);
        $first = reset($disks);
        foreach (['id', 'blocks', 'bytes', 'formated'] as $field) {
            $this->assertArrayHasKey($field, $first);
        }
        $this->assertGreaterThan(0, $first['bytes']);
    }

    /**
     * @group disk
     */
    public function testVolumesInfo(): void
    {
        $volumes = Info::get()->volumesInfo();
        $this->assertNotEmpty($volumes);
        $first = reset($volumes);
        foreach (['disk', 'mount', 'file_system', 'total_space_bytes', 'free_space_bytes', 'used_space_bytes', 'used_percent'] as $field) {
            $this->assertArrayHasKey($field, $first);
        }
        $this->assertGreaterThanOrEqual(0, $first['used_percent']);
        $this->assertLessThanOrEqual(100, $first['used_percent']);
    }

    // -------------------------------------------------------------------------
    // Processes
    // -------------------------------------------------------------------------

    /**
     * @group processes
     */
    public function testProcesses(): void
    {
        $all = Info::get()->processes();
        $this->assertNotEmpty($all);

        $last = end($all);
        $this->assertArrayHasKey('stat',   $last);
        $this->assertArrayHasKey('status', $last);
    }

    /**
     * @group processes
     */
    public function testProcessesFilteredByStat(): void
    {
        $result = Info::get()->processes(['pid'], 'stat');
        $this->assertNotEmpty($result);
        $first = reset($result);
        $this->assertArrayHasKey('pid', $first);
        $this->assertArrayNotHasKey('comm', $first);
    }

    /**
     * @group processes
     */
    public function testProcessesFilteredByStatus(): void
    {
        $result = Info::get()->processes(['pid'], 'status');
        $this->assertNotEmpty($result);
        $first = reset($result);
        $this->assertArrayHasKey('pid', $first);
    }

    /**
     * @group processes
     */
    public function testProcessesRunningOnly(): void
    {
        $statRunning   = Info::get()->processes(['pid'], 'stat',   true);
        $statusRunning = Info::get()->processes(['pid'], 'status', true);
        $this->assertIsArray($statRunning);
        $this->assertIsArray($statusRunning);
    }

    /**
     * @group processes
     */
    public function testSingleProcess(): void
    {
        $proc = Info::get()->process(1);
        $this->assertNotEmpty($proc);
        $this->assertArrayHasKey('stat',   $proc);
        $this->assertArrayHasKey('status', $proc);
    }

    /**
     * @group processes
     */
    public function testSingleProcessFilteredByStat(): void
    {
        $proc = Info::get()->process(1, ['pid'], 'stat');
        $this->assertArrayHasKey('pid', $proc);
    }

    /**
     * @group processes
     */
    public function testSingleProcessFilteredByStatus(): void
    {
        $proc = Info::get()->process(1, ['pid'], 'status');
        $this->assertArrayHasKey('pid', $proc);
    }

    /**
     * @group processes
     */
    public function testProcessesActiveOrRunning(): void
    {
        $result = Info::get()->processesActiveOrRunning(
            ['comm', 'state', 'pid', 'ppid', 'vsize', 'processor', 'cpu_usage'],
            'stat'
        );
        $this->assertIsArray($result);
    }

    // -------------------------------------------------------------------------
    // Network
    // -------------------------------------------------------------------------

    /**
     * @group network
     */
    public function testNetworkInterfaces(): void
    {
        $interfaces = Info::get()->networks();
        $this->assertNotEmpty($interfaces);

        $first = reset($interfaces);
        // Interface rows include at minimum the interface name column
        $this->assertIsArray($first);
        $this->assertNotEmpty($first);
    }

    /**
     * @group network
     */
    public function testNetworkInterfaceColumns(): void
    {
        // Standard /proc/net/dev columns
        $interfaces = Info::get()->networks(['bytes', 'bytes_out', 'packets', 'packets_out']);
        $this->assertNotEmpty($interfaces);

        $first = reset($interfaces);
        $this->assertArrayHasKey('bytes',       $first);
        $this->assertArrayHasKey('bytes_out',   $first);
        $this->assertArrayHasKey('packets',     $first);
        $this->assertArrayHasKey('packets_out', $first);
    }

    /**
     * @group network
     */
    public function testTcpConnections(): void
    {
        $connections = Info::get()->tcpConnections(true);
        // May be empty if no active connections; just verify the return type
        $this->assertIsArray($connections);

        foreach ($connections as $conn) {
            $this->assertArrayHasKey('local_ip',    $conn);
            $this->assertArrayHasKey('local_port',  $conn);
            $this->assertArrayHasKey('remote_ip',   $conn);
            $this->assertArrayHasKey('remote_port', $conn);
        }
    }

    /**
     * @group network
     */
    public function testTcpConnectionsSummarized(): void
    {
        $summary = Info::get()->tcpConnectionsSummarized(true);
        $this->assertIsArray($summary);

        foreach ($summary as $entry) {
            $this->assertArrayHasKey('ip',          $entry);
            $this->assertArrayHasKey('port',        $entry);
            $this->assertArrayHasKey('connections', $entry);
            $this->assertGreaterThan(0, $entry['connections']);
        }
    }

    // -------------------------------------------------------------------------
    // GPU
    // -------------------------------------------------------------------------

    /**
     * @group gpu
     */
    public function testGpuInfo(): void
    {
        $gpus = Info::get()->gpuInfo();
        $this->assertIsArray($gpus);

        foreach ($gpus as $card => $info) {
            $this->assertStringStartsWith('card', $card);
            $this->assertArrayHasKey('vendor', $info);
            $this->assertArrayHasKey('vendor_id', $info);
            $this->assertArrayHasKey('device_id', $info);
        }
    }

    // -------------------------------------------------------------------------
    // Formatter
    // -------------------------------------------------------------------------

    /**
     * @group formatter
     */
    public function testFormatBytes(): void
    {
        // Values ≤ 0 are returned unchanged (not formatted)
        $this->assertSame(0,    Formatter::bytes(0));
        $this->assertSame(-10, Formatter::bytes(-10));

        $this->assertSame('1 KB',   Formatter::bytes(1024));
        $this->assertSame('1 MB',   Formatter::bytes(1024 ** 2));
        $this->assertSame('1 GB',   Formatter::bytes(1024 ** 3));

        // Static facade proxy
        $this->assertSame('1 KB', Info::formatBytes(1024));
    }
}
