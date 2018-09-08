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
        $other = Info::get()->otherInfo();
        $this->assertNotEmpty($other);
    }

    /**
     * @group cpu
     */
    public function testCpuInfo() {
        $cpuinfo = Info::get()->cpuInfo();
        $this->assertNotEmpty($cpuinfo);

        $cpuinfoless = Info::get()->cpuInfo(0, ['model_name']);
        $this->assertNotEmpty($cpuinfoless[0]['model_name']);
    }

    /**
     * @group cpu
     */
    public function testCpuLoad() {

        //Lets find some prime numbers.
        for ($i = 1; $i <= 100; $i++) {
            echo $i;
        }

        $cpuload = Info::get()->cpuLoad(2, 4);
        var_dump($cpuload);
    }


    /**
     * used for stress test.
     *
     * https://stackoverflow.com/a/16763365/4824540
     */
    private function isPrime($num) {
        if($num == 1)
            return false;

        //2 is prime (the only even number that is prime)
        if($num == 2)
            return true;

        /**
         * if the number is divisible by two, then it's not prime and it's no longer
         * needed to check other even numbers
         */
        if($num % 2 == 0) {
            return false;
        }

        /**
         * Checks the odd numbers. If any of them is a factor, then it returns false.
         * The sqrt can be an aproximation, hence just for the sake of
         * security, one rounds it to the next highest integer value.
         */
        $ceil = ceil(sqrt($num));
        for($i = 3; $i <= $ceil; $i = $i + 2) {
            if($num % $i == 0)
                return false;
        }

        return true;
    }
}