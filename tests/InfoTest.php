<?php
/**
 * Created by Daniel Mellum <mellum@gmail.com>
 * Date: 9/7/2018
 * Time: 9:36 PM
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use danielme85\Server\Info;

final class InfoTest extends TestCase
{

    /**
     * @group init
     */
    public function testInitAndSettingOverride() {
        $info = new Info(
            ['ext4']
        );
        $filesystems = $info->fileSystemTypes();
        $this->assertContains('ext4', $filesystems);
        $this->assertCount(1, $filesystems);
    }

    /**
     * @group uptime
     */
    public function testUptime() {
        $uptime = Info::get()->uptime(true);

        $this->assertNotEmpty($uptime);
        $this->assertGreaterThan(1, $uptime['started_unix']);
        $this->assertGreaterThan(1, $uptime['current_unix']);
        $this->assertGreaterThan(1, $uptime['uptime_unix']);
        $this->assertNotEmpty($uptime['uptime']);
        $this->assertNotEmpty($uptime['uptime_text']);
    }

    /**
     * @group other
     */
    public function testOtherInfo() {
        $this->assertNotEmpty(Info::get()->otherInfo());
    }

    /**
     * @group cpu
     */
    public function testCpuInfo() {
        $this->assertNotEmpty(Info::get()->cpuInfo());

        $cpuinfoless = Info::get()->cpuInfo(0, ['model_name']);
        $this->assertNotEmpty($cpuinfoless['model_name']);
    }

    /**
     * @group cpu
     */
    public function testCpuLoad() {
        //run a small stress test.
        exec("php ../PrimeStress.php > /dev/null 2>/dev/null &");
        $cpuload = Info::get()->cpuLoad(1, 8);
        $this->assertGreaterThan(0, $cpuload['cpu']['load']);
    }

    /**
     * @group memory
     */
    public function testMemoryUsageAndLoad() {
        $this->assertNotEmpty(Info::get()->memoryUsage());
        $this->assertNotEmpty(Info::get()->memoryUsage(false));
        $memoryload = Info::get()->memoryLoad(0);
        $this->assertGreaterThan(0, $memoryload['load']);
    }

    /**
     * @group memory
     */
    public function testMemoryInfo() {
        $this->assertNotEmpty(Info::get()->memoryInfo());
    }

    /**
     * @group disk
     */
    public function testDiskInfo() {
        $this->assertNotEmpty(Info::get()->diskInfo());
    }

    /**
     * @group disk
     */
    public function testVolumes() {
        $this->assertNotEmpty(Info::get()->volumesInfo());
    }

    /**
     * @group processes
     */
    public function testProcesses() {
        $allprocs = Info::get()->processes();
        $lastofall = end ($allprocs);

        $this->assertNotEmpty($lastofall['stat']);
        $this->assertNotEmpty($lastofall['status']);

        //test process pid 1
        $firstproc = Info::get()->process(1);
        $this->assertNotEmpty($firstproc['stat']);
        $this->assertNotEmpty($firstproc['status']);

        //test filtered returns
        $this->assertNotEmpty(Info::get()->processes(['pid']));
        $this->assertNotEmpty(Info::get()->processes(['pid'], 'status'));
        $this->assertNotEmpty(Info::get()->processes(['pid'], 'stat'));
        $this->assertNotEmpty(Info::get()->processes(['pid'], 'status', true));
        $this->assertNotEmpty(Info::get()->processes(['pid'], 'stat', true));
        $this->assertNotEmpty(Info::get()->process(1, ['pid'], 'stat'));
        $this->assertNotEmpty(Info::get()->process(1, ['pid'], 'status'));

        //Test activeOrRunning & cpi_usage load.
        $this->assertNotEmpty(Info::get()->processesActiveOrRunning(['comm', 'state', 'pid', 'ppid','vsize', 'processor', 'cpu_usage'], 'stat'));

    }

    /**
     * @group network
     */
    public function testNetwork() {
        //test network interfaces, and load avg calcs
        $this->assertNotEmpty(Info::get()->networks());
        $this->assertNotEmpty(['face', 'bytes', 'packets' ,'packets_out', 'bytes_out', 'packets-out', 'load', 'load_out']);

        //test tcp active connections
        $this->assertNotEmpty(Info::get()->tcpConnections());
        $this->assertNotEmpty(Info::get()->tcpConnections(false));
        $this->assertNotEmpty(Info::get()->tcpConnectionsSummarized());

    }
}