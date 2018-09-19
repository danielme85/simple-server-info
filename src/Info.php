<?php

namespace danielme85\Server;

/**
 * Class SysInfo
 *
 * This is a collection of functions to read of system information from Procfs.
 * On supported operating systems there are text files in a simulated folder structure under /proc/*.
 * This class simply reads this information. No shell args or other hacky things used.
 * The methods used here will naturally only work on an OS that supports and uses Procfs.
 *
 * Additional information and sources used to develop this class:
 * https://en.wikipedia.org/wiki/Procfs
 * https://en.wikipedia.org/wiki/Load_(computing)
 * https://stackoverflow.com/questions/23367857/accurate-calculation-of-cpu-usage-given-in-percentage-in-linux
 * https://stackoverflow.com/questions/16726779/how-do-i-get-the-total-cpu-usage-of-an-application-from-proc-pid-stat
 * https://www.systutorials.com/docs/linux/man/5-proc/
 * https://gist.github.com/jkstill/5095725
 *
 * @package danielme85\LaraStats\Models
 */
class Info
{
    /**
     * @var string Base path for the virtual /proc system.
     */
    private $basePath = '/proc';

    /**
     * There are a lot of filesystems mounted by Linux (50+ on a simple webserver),
     * lets just include the types we are interested in.
     *
     * @var array Included file system types in volumes info.
     */
    private $showFileSystemTypes = ['ext', 'ext2', 'ext3', 'ext4', 'fat32', 'ntfs', 'vboxsf'];

    /**
     * SysInfo constructor.
     *
     * @param array|null $showFileSystemTypes Set the file system types to include.
     */
    public function __construct(array $showFileSystemTypes = null)
    {
        if (!empty($showFileSystemTypes)) {
            $this->showFileSystemTypes = $showFileSystemTypes;
        }
    }

    /**
     * Static wrapper for self
     *
     * @return Info
     */
    public static function get() {
        return new self;
    }

    /**
     * Get the list of file systems we are looking for.
     *
     * @return array
     */
    public function fileSystemTypes() {

        return $this->showFileSystemTypes;
    }

    /**
     * Get the current uptime information
     *
     * @param bool $formated Return some date formated values in addition to unix times.
     *
     * @return array
     */
    public function uptime($formated = true) : array
    {
        $time = time();
        $uptimeArray = $this->getProcUptime();

        if (!empty($uptimeArray)) {
            $uptimeunix = (int)$uptimeArray[0];
            $startedunix = $time-$uptimeunix;

            $results['current_unix'] = $time;
            $results['uptime_unix'] = $uptimeunix;
            $results['started_unix'] = $startedunix;

            if ($formated) {
                $started = date('Y-m-d H:i:s', $startedunix);
                $current = date('Y-m-d H:i:s');

                $datetimeStarted = new \DateTime($started);
                $datetimeNow = new \DateTime($current);

                $interval = $datetimeStarted->diff($datetimeNow);

                $results['started'] = $started;
                $results['current'] = $current;
                $results['uptime'] = $interval->format("%a:%H:%I:%S") ?? null;
                $results['uptime_text'] = $interval->format('%a days, %h hours, %i minutes and %s seconds');
            }

        }

        return $results ?? [];
    }

    /**
     * Get current uptime.
     *
     * @return array
     */
    private function getProcUptime() : array
    {
        $uptimeRaw = $this->readFileLines("$this->basePath/uptime");

        if (!empty($uptimeRaw[0])) {
            $uptimeArray = explode(' ', $uptimeRaw[0]);
        }

        return $uptimeArray ?? [];
    }

    /**
     * Get other information: OS signatures.
     *
     * @return array
     */
    public function otherInfo() {
        return $this->getProcOtherInfo();
    }

    /**
     * Get misc other info
     *
     * @return array
     */
    private function getProcOtherInfo() : array
    {

        $version = $this->readFileLines("$this->basePath/version");
        $versionSignature = $this->readFileLines("$this->basePath/version_signature");

        return [
            'version' => $version[0] ?? null,
            'version_signature' => $versionSignature[0] ?? null
        ];
    }

    /**
     * Get information on the CPU (per core)
     *
     * @param int|null $core Get info for this core only (null = all cores)
     * @param array|null $returnonly Only return specified columns.
     *
     * @return array
     */
    public function cpuInfo(int $core = null, array $returnonly = null) : array
    {
        if (isset($core) and is_int($core)) {
            $cores = $this->getProcCpuInfo($returnonly);
            return $cores[$core] ?? [];
        }

        return $this->getProcCpuInfo($returnonly);
    }

    /**
     * Get CPU load information based on sample wait time in sec.
     *
     * @param int $sampleSec wait time between two samples of the work load for the CPU.
     * @param null|int $rounding, set the rounding precision for the cpu % load results.
     *
     * @return array
     */
    public function cpuLoad($sampleSec = 1, $rounding = null) : array
    {
        $measure1 = $this->getProcStat();
        sleep($sampleSec);
        $measure2 = $this->getProcStat();

        return $this->calculateCpuUsage($measure1, $measure2, $rounding);
    }

    /**
     * Get CPU information.
     *
     * @param array|null $returnonly Only return column headers in this array.
     * @return array
     */
    private function getProcCpuInfo(array $returnonly = null) : array
    {
        $cpu = 0;

        $info = $this->readFileLines("$this->basePath/cpuinfo");
        if (!empty($info)) {
            foreach ($info as $row) {
                if (empty($row)) {
                    $cpu++;
                }
                else {
                    $keypos = strpos($row, ':');
                    $key = trim(strtolower(str_replace(' ', '_', substr($row, 0, $keypos))));
                    if (empty($returnonly) or in_array($key, $returnonly)) {
                        $value = trim(str_replace(':', '', substr($row, $keypos)));
                        $results[$cpu][$key] = $value;
                    }
                }
            }
        }

        return $results ?? [];
    }

    /**
     *
     * @return float
     */
    public function processesCpuUsage($runningonly = false)
    {
        $pidTimeFirsts = $this->processes(['pid', 'utime', 'stime', 'processor'], 'stat', $runningonly);
        $totalTimeFirsts = $this->getProcStat();
        sleep(1);
        $pidTimeSecond = $this->processes(['pid', 'utime', 'stime', 'processor'], 'stat', $runningonly);
        $totalTimeSecond= $this->getProcStat();

        if (!empty($pidTimeFirsts)) {
            foreach ($pidTimeFirsts as $pidRow) {
                $results[(string)$pidRow['pid']] = $this->calculateProcessesCpuUsage($pidRow, $pidTimeSecond[$pidRow['pid']], $totalTimeFirsts, $totalTimeSecond);
            }
        }

        return $results ?? [];
    }

    /**
     *
     * @param $pidTimeFirst
     * @param $pidTimeSecond
     * @param $totalTimeFirst
     * @param $totalTimeSecond
     * @return float
     */
    private function calculateProcessesCpuUsage($pidTimeFirst, $pidTimeSecond, $totalTimeFirst, $totalTimeSecond) {
        $utimefirst = $pidTimeFirst['utime'] + $pidTimeFirst['stime'];
        $utimesecond = $pidTimeSecond['utime'] + $pidTimeSecond['stime'];;

        $cpu = $pidTimeFirst['processor'];

        $totalFirst = $totalTimeFirst['cpu']["cpu$cpu"];
        $totalSecond = $totalTimeSecond['cpu']["cpu$cpu"];

        $totalFirstTime = 0;
        $totalSecondTime = 0;

        if (!empty($totalFirst)) {
            foreach ($totalFirst as $first) {
                $totalFirstTime += $first;
            }
        }
        if (!empty($totalSecond)) {
            foreach ($totalSecond as $second) {
                $totalSecondTime += $second;
            }
        }

        if ($totalTimeFirst > 0 and $totalSecondTime > 0) {
            $usage = 100 * ($utimesecond - $utimefirst) / ($totalSecondTime - $totalFirstTime);
        }
        else {
            $usage = 0;
        }

        return round($usage, 2);
    }

    /**
     * Get Memory Load
     *
     * @param int|null rounding.
     * @return array
     */
    public function memoryLoad($rounding = 2) : array
    {
        $memory = $this->getProcMemInfo();
        $total = $memory['MemTotal'];
        $free = $memory['MemAvailable'];
        $used = $total - $free;

        $totalSwap = $memory['SwapTotal'];
        $freeSwap = $memory['SwapFree'];
        $swapUsed = $totalSwap - $freeSwap;

        if ($total > 0) {
            $usage = ($used/$total) * 100;
        }
        else {
            $usage = 0;
        }

        if ($totalSwap > 0) {
            $swap = ($swapUsed/$totalSwap) * 100;
        }
        else {
            $swap = 0;
        }

        return [
            'load' => round($usage, $rounding),
            'swap_load' => round($swap, $rounding)
        ];
    }

    /**
     * Get Memory usage information
     * @param bool $formatSizes set to false if you want bytes returned instead of formatted sizes.
     * @return array
     */
    public function memoryUsage($formatSizes = true) : array
    {
        $memory = $this->getProcMemInfo();
        if (!empty($memory)) {
            if ($formatSizes) {
                return [
                    'total' => self::formatBytes($memory['MemTotal']),
                    'free' => self::formatBytes($memory['MemFree']),
                    'available' => self::formatBytes($memory['MemAvailable']),
                    'used' => self::formatBytes($memory['MemTotal']-$memory['MemAvailable']),
                    'cached' => self::formatBytes($memory['Cached']),
                    'active' => self::formatBytes($memory['Active']),
                    'inactive' => self::formatBytes($memory['Inactive']),
                    'swap_total' => self::formatBytes($memory['SwapTotal']),
                    'swap_free' => self::formatBytes($memory['SwapFree'])
                ];
            }
            else {
                return [
                    'total' => $memory['MemTotal'],
                    'free' => $memory['MemFree'],
                    'available' => $memory['MemAvailable'],
                    'used' => $memory['MemTotal']-$memory['MemAvailable'],
                    'cached' => $memory['Cached'],
                    'active' => $memory['Active'],
                    'inactive' => $memory['Inactive'],
                    'swap_total' => $memory['SwapTotal'],
                    'swap_free' => $memory['SwapFree']
                ];
            }
        }
        return [];
    }

    /**
     * Get all Memory information
     *
     * @return array
     */
    public function memoryInfo() : array
    {
        return $this->getProcMemInfo() ?? [];
    }

    /**
     * Get memory information.
     *
     * @return array|null
     */
    private function getProcMemInfo()
    {
        $memory = $this->readFileLines("$this->basePath/meminfo");
        if (!empty($memory)) {
            foreach ($memory as $row) {
                if (!empty($row)) {
                    $keypos = strpos($row, ':');
                    $key = substr($row, 0, $keypos);
                    $value = (int)preg_replace('/[^0-9]/', '', substr($row, $keypos)) * 1000;
                    $results[$key] = $value;
                }
            }
        }

        return $results ?? [];
    }

    /**
     * Get info on disks
     *
     * @return mixed
     */
    public function diskInfo() {
        $partitions = $this->getProcPartitions();

        if (!empty($partitions)) {
            foreach ($partitions as $partition) {
                if (!empty($partition)) {
                    $newrow['id'] = $partition['major'].':'.$partition['minor'];
                    $newrow['blocks'] = $partition['#blocks'];
                    $newrow['bytes'] = $newrow['blocks']*1024;
                    $newrow['formated'] = self::formatBytes($newrow['bytes']);


                    $results[$partition['name']] = $newrow;
                }
            }
        }

        return $results;
    }

    /**
     * Get disk partition information
     *
     * @return array
     */
    private function getProcPartitions() : array
    {
        $partitionsRaw = $this->readFileLines("$this->basePath/partitions");
        if (!empty($partitionsRaw)) {
            $first = true;
            $counter = 0;
            foreach ($partitionsRaw as $row) {
                if (!empty($row)) {
                    if ($first) {
                        $headers = explode(' ', preg_replace('!\s+!', ' ', trim($row)));
                        $first = false;
                    } else {
                        $values = explode(' ', preg_replace('!\s+!', ' ', trim($row)));
                        if (!empty($headers) and !empty($values)) {
                            $subcounter = 0;
                            foreach ($headers as $header) {
                                $results[$counter][$header] = $values[$subcounter];
                                $subcounter++;
                            }
                        }
                        $counter++;
                    }
                }
            }
        }

        return $results ?? [];
    }

    /**
     * Get mounting information.
     *
     * @return array
     */
    private function getProcMounts() : array
    {
        $mountsRaw = $this->readFileLines("$this->basePath/mounts");

        if (!empty($mountsRaw)) {
            foreach ($mountsRaw as $row) {
                if (!empty($row)) {
                    $values = explode(' ', preg_replace('!\s+!', ' ', trim($row)));
                    if (!empty($values)) {
                        $newrow['mount'] = $values[1];
                        $newrow['disk'] = $values[0];
                        $newrow['file_system'] = $values[2];

                        $results[] = $newrow;
                    }
                }
            }
        }

        return $results ?? [];
    }

    /**
     * Get info on storage volumes
     *
     * @return array
     */
    public function volumesInfo() : array
    {
        $mounts = $this->getProcMounts();
        if (!empty($mounts)) {
            foreach ($mounts as $mount) {
                if (in_array($mount['file_system'], $this->showFileSystemTypes)) {
                    $mount['total_space_bytes'] =  disk_total_space($mount['mount']);
                    $mount['total_space'] =  self::formatBytes($mount['total_space_bytes']);
                    $mount['free_space_bytes'] =  disk_free_space($mount['mount']);
                    $mount['free_space'] =  self::formatBytes($mount['free_space_bytes']);
                    $mount['used_space_bytes'] = $mount['total_space_bytes'] - $mount['free_space_bytes'];
                    $mount['used_space'] = self::formatBytes($mount['used_space_bytes']);
                    $usage = ($mount['used_space_bytes']/$mount['total_space_bytes']) * 100 ?? 0;
                    $mount['used_percent'] = round($usage, 2);

                    $results[] = $mount;
                }
            }
        }

        return $results ?? [];
    }

    /**
     * @param int $pid the process pid
     * @param array|null $returnonly An array of columns wanted. Null=all
     * @param string|null $returntype An array of type wanted 'status' or 'stat'. Null=both.
     * @return array
     */
    public function process(int $pid, array $returnonly = null, string $returntype = null) : array
    {
        if ($returntype === 'stat') {
            return $this->getProcessStats($pid, $returnonly, false);
        }
        if ($returntype === 'status') {
            return $this->getProcessStatus($pid, $returnonly, false);
        }
        return [
            'status' => $this->getProcessStatus($pid, $returnonly, false),
            'stat'=> $this->getProcessStats($pid, $returnonly, false)
            ];
    }

    /**
     * Get all running processes.
     *
     * @param array|null $returnonly An array of columns wanted. Null=all
     * @param string|null $returntype An array of type wanted 'status' or 'stat'. Null=both.
     * @param bool $runningonly Only return running processes.
     * @return array
     */
    public function processes($returnonly = null, string $returntype = null, $runningonly = false) : array
    {
        return $this->getProcesses($returnonly, $returntype, $runningonly);
    }

    /**
     * Get all active processes.
     *
     * @param array|null $returnonly An array of columns wanted. Null=all
     * @param string|null $returntype An array of type wanted 'status' or 'stat'. Null=both.
     * @param bool $runningonly Only return running processes.
     * @return array
     */
    public function processesActiveOrRunning($returnonly = null, string $returntype = null) : array
    {
        //set min required data if we want to get active processes or running.
        if (!in_array('cpu_usage', $returnonly)) {
            $returnonly[] = 'cpu_usage';
        }
        if (!in_array('state', $returnonly)) {
            $returnonly[] = 'state';
        }


        $processes = $this->getProcesses($returnonly, $returntype, false);

        if (empty($returntype)) {
            foreach ($processes as $type => $returnvalues) {
                $results[$type] = $this->filterActiveOrRunning($returnvalues);
            }
        }
        else {
            $results = $this->filterActiveOrRunning($processes);
        }

        return $results ?? [];
    }

    /**
     * @param $processes
     * @return array
     */
    private function filterActiveOrRunning($processes) : array
    {
        if (!empty($processes)) {
            foreach ($processes as $pid => $info) {
                if (!empty($info) and isset($info['state'])) {
                    if ($info['cpu_usage'] > 0 or $info['state'] === 'R' or $info['state'] === 'R (running)') {
                        $results[$pid] = $info;
                    }
                }
            }
        }

        return $results ?? [];
    }

    /**
     * Get detailed process info from /proc/{$pid}/status
     *
     * @param string $pid
     * @param array|null $returnonly An array of columns wanted. Null=all
     * @return array
     */
    private function getProcessStatus($pid, $returnonly, $runningonly) : array
    {
        if ($runningonly and !empty($returnonly)) {
            if (!in_array('state', $returnonly)) {
                $returnonly[] = 'state';
            }
        }
        $statcontent = $this->readFileLines("$this->basePath/$pid/status");
        if (!empty($statcontent)) {
            foreach ($statcontent as $row) {
                if (!empty($row)) {
                    $keypos = strpos($row, ':');
                    $key = trim(strtolower(str_replace(' ', '_', substr($row, 0, $keypos))));
                    if (empty($returnonly) or in_array($key, $returnonly)) {
                        $value = trim(str_replace(':', '', substr($row, $keypos)));
                        $results[$key] = $value;
                    }
                }
            }
            if (!$runningonly or $results['state'] === 'R (running)') {
                return $results ?? [];
            }
        }

        return [];
    }

    /**
     * Get additional process runtime info from /proc/{$pid}/stats
     *
     * @param string $pid
     * @param array|null $returnonly An array of columns wanted. Null=all
     * @return array
     */
    private function getProcessStats($pid, $returnonly, $runningonly) : array
    {
        if ($runningonly and !empty($returnonly)) {
            if (!in_array('state', $returnonly)) {
                $returnonly[] = 'state';
            }
        }
        $headers = [
            'pid', 'comm', 'state', 'ppid', 'pgrp', 'session', 'tty_nr', 'tpgid', 'flags', 'minflt', 'cminflt', 'majflt',
            'cmajflt', 'utime', 'stime', 'cutime', 'cstime', 'priority', 'nice', 'num_threads', 'itrealvalue',
            'starttime', 'vsize', 'rss', 'rsslim', 'startcode', 'endcode', 'startstack', 'kstkesp', 'kstkeip', 'signal',
            'blocked', 'sigignore', 'sigcatch', 'wchan', 'nswap', 'cnswap', 'exit_signal', 'processor', 'rt_priority',
            'policy', 'delayacct_blkio_ticks', 'guest_time', 'cguest_time'
        ];

        $statcontent = $this->readFileLines("$this->basePath/$pid/stat");
        if (!empty($statcontent)) {
            if (!empty($statcontent[0])) {
                $statArray = explode(' ', $statcontent[0]);
                if (!empty($statArray)) {
                    if (!$runningonly or ($statArray[2] === 'R')) {
                        $i = 0;
                        foreach ($headers as $header) {
                            if (empty($returnonly) or in_array($header, $returnonly)) {
                                $results[$header] = $statArray[$i] ?? null;
                            }
                            $i++;
                        }
                    }
                }
            }
        }

        return $results ?? [];
    }

    /**
     * Get all running processes.
     *
     * @param array|null $returnonly An array of columns wanted. Null=all
     * @param string|null $returntype An array of type wanted 'status' or 'stat'. Null=both.
     * @param bool $runningonly Only return running processes.
     *
     * @return array
     */
    private function getProcesses($returnonly = null, $returntype = null, $runningonly = false) : array
    {
        $list = $this->getProcessList();

        if (!empty($list)) {
            foreach ($list as $pid) {
                if ($returntype === 'stat') {
                    $stat = $this->getProcessStats($pid, $returnonly, $runningonly);
                    if (!empty($stat)) {
                        $results[$pid] = $stat;
                    }

                } else if ($returntype === 'status') {
                    $status = $this->getProcessStatus($pid, $returnonly, $runningonly);
                    if (!empty($status)) {
                        $results[$pid] = $status;
                    }
                } else {
                    $stat = $this->getProcessStats($pid, $returnonly, $runningonly);
                    $status = $this->getProcessStatus($pid, $returnonly, $runningonly);

                    if (!empty($status) or !empty($stats)) {
                        $results[$pid] = [
                            'status' => $status,
                            'stat' => $stat
                        ];
                    }
                }
            }
            if (!empty($returnonly)) {
                if (in_array('cpu_usage', $returnonly)) {
                    $cpuUsage = $this->processesCpuUsage($runningonly);
                    if (!empty($cpuUsage)) {
                        foreach ($cpuUsage as $pidRow => $usageRow) {
                            $results[$pidRow]['cpu_usage'] = $usageRow;
                        }
                    }
                }
            }
        }

        return $results ?? [];
    }

    /**
     * Get a list of running processes by scanning /proc
     *
     * @return array
     */
    private function getProcessList() : array
    {
        $scan = scandir($this->basePath);
        if (!empty($scan)) {
            foreach ($scan as $row) {
                if (is_numeric($row)) {
                    $processes[]=(int)$row;
                }
            }
        }
        sort($processes);

        return $processes ?? [];
    }

    /**
     * Calculate CPU percent usage based on two sample datasets
     *
     * @param array $measure1
     * @param array $measure2
     * @param int $rounding
     * @return array
     */
    private function calculateCpuUsage(array $measure1, array $measure2, $rounding = 2) : array
    {
        $measurements = [];

        if (!empty($measure1['cpu'])) {
            foreach ($measure1['cpu'] as $cpu => $measure) {
                $measurements[$cpu][0] = $measure;
            }
        }
        if (!empty($measure2['cpu'])) {
            foreach ($measure2['cpu'] as $cpu => $measure) {
                $measurements[$cpu][1] = $measure;
            }
        }

        if (!empty($measurements)) {
            $first = true;
            $core = 0;

            foreach ($measurements as $cpu => $row) {
                $prevIdle = $row[0]['idle'] +  $row[0]['guest'] + $row[0]['guest_nice'];
                $lastIdle = $row[1]['idle'] +  $row[1]['guest'] + $row[1]['guest_nice'];

                $prevActive = $row[0]['user'] + $row[0]['nice'] + $row[0]['system'] + $row[0]['irq'] + $row[0]['softirq'] + $row[0]['steal'] + $row[0]['iowait'];
                $lastActive = $row[1]['user'] + $row[1]['nice'] + $row[1]['system'] + $row[1]['irq'] + $row[1]['softirq'] + $row[1]['steal'] + $row[1]['iowait'];

                $prevTotal = $prevActive + $prevIdle;
                $lastTotal = $lastActive + $lastIdle;

                $totalDiff = $lastTotal - $prevTotal;
                $idleDiff = $lastIdle - $prevIdle;

                if ($totalDiff > 0) {
                    $cpuPercentage = ($totalDiff - $idleDiff)/$totalDiff;
                }
                else {
                    $cpuPercentage = $totalDiff - $idleDiff;
                }

                if ($first) {
                    $label = 'CPU';
                    $first = false;
                }
                else {
                    $label = "Core#$core";
                    $core++;
                }

                $results[$cpu]['label'] = $label;
                $results[$cpu]['load'] = round($cpuPercentage*100, $rounding);
            }
        }

        return $results ?? [];
    }

    /**
     * Get CPU load information.
     *
     * @return array
     */
    private function getProcStat(): array
    {
        $cpu = $this->readFileLines("$this->basePath/stat");
        if (!empty($cpu)) {
            foreach ($cpu as $cpuline) {
                //check for cpu*
                if (strpos($cpuline, 'cpu') === 0) {
                    //clear double space
                    $cpuline = str_replace('  ', ' ', $cpuline);
                    $line = explode(' ', $cpuline);
                    if (!empty($line)) {
                        $results['cpu'][$line[0]]['user'] = (int)$line[1] ?? 0;
                        $results['cpu'][$line[0]]['nice'] = (int)$line[2] ?? 0;
                        $results['cpu'][$line[0]]['system'] = (int)$line[3] ?? 0;
                        $results['cpu'][$line[0]]['idle'] = (int)$line[4] ?? 0;
                        $results['cpu'][$line[0]]['iowait'] = (int)$line[5] ?? 0;
                        $results['cpu'][$line[0]]['irq'] = (int)$line[6] ?? 0;
                        $results['cpu'][$line[0]]['softirq'] = (int)$line[7] ?? 0;
                        $results['cpu'][$line[0]]['steal'] = (int)$line[8] ?? 0;
                        $results['cpu'][$line[0]]['guest'] = (int)$line[9] ?? 0;
                        $results['cpu'][$line[0]]['guest_nice'] = (int)$line[10] ?? 0;
                    }
                } else if (strpos($cpuline, 'ctxt') === 0) {
                    $results['ctxt'] = (int)preg_replace('/[^0-9]/', '', $cpuline);
                } else if (strpos($cpuline, 'btime') === 0) {
                    $results['btime'] = (int)preg_replace('/[^0-9]/', '', $cpuline);
                } else if (strpos($cpuline, 'processes') === 0) {
                    $results['processes'] = (int)preg_replace('/[^0-9]/', '', $cpuline);
                } else if (strpos($cpuline, 'procs_running') === 0) {
                    $results['procs_running'] = (int)preg_replace('/[^0-9]/', '', $cpuline);
                } else if (strpos($cpuline, 'procs_blocked') === 0) {
                    $results['procs_blocked'] = (int)preg_replace('/[^0-9]/', '', $cpuline);
                }
            }
        }


        return $results ?? [];
    }

    /**
     * Return a summarized version of tcpConnections.
     *
     * @param bool $includeLocalhost Include localhost connections. Default=false
     * @return array
     */
    public function tcpConnectionsSummarized(bool $includeLocalhost = false) : array
    {
        $results = [];

        $connections = $this->tcpConnections($includeLocalhost);

        if (!empty($connections)) {
            foreach ($connections as $connection) {
                $hash = md5($connection['local_ip'].$connection['local_port']);
                $results[$hash]['ip'] = $connection['local_ip'];
                $results[$hash]['port'] = $connection['local_port'];
                if (isset($results[$hash]['connections'])) {
                    $results[$hash]['connections'] += 1;
                }
                else {
                    $results[$hash]['connections'] = 1;
                }
            }
        }

        return $results;
    }

    /**
     * Return a list of all tcp connections
     *
     * @param bool $includeLocalhost Include localhost connections. Default=false
     * @return array
     */
    public function tcpConnections(bool $includeLocalhost = false) : array
    {
        $tcp = $this->getProcTcpConnections();
        if (!empty($tcp)) {
            foreach ($tcp as $row) {
                $local = $this->convertTcpIpFormat($row['local_address']);
                $newrow['local_ip'] = $local['ip'];
                $newrow['local_port'] = $local['port'];

                $remote = $this->convertTcpIpFormat($row['rem_address']);
                $newrow['remote_ip'] = $remote['ip'];
                $newrow['remote_port'] = $remote['port'];

                $include = true;
                //Ignore dummy connections
                if ($newrow['local_ip'] === '0.0.0.0' or $newrow['remote_ip'] === '0.0.0.0') {
                    $include = false;
                }
                //Ignore localhost connections
                if (!$includeLocalhost) {
                    if ($newrow['local_ip'] === '127.0.0.1' or $newrow['remote_ip'] === '127.0.0.1') {
                        $include = false;
                    }
                }
                if ($include) {
                    $results[] = $newrow;
                }
            }
        }

        return $results ?? [];
    }

    /**
     * Helper for tcp connections
     *
     * @param $input
     * @return array
     */
    private function convertTcpIpFormat($input) {
        $ip = '';
        $ip .= hexdec(substr($input, 6, 2)). '.';
        $ip .= hexdec(substr($input, 4, 2)). '.';
        $ip .= hexdec(substr($input, 2, 2)). '.';
        $ip .= hexdec(substr($input, 0, 2));
        $port = hexdec(substr($input, 9, 4));

        return ['ip' => $ip, 'port' => $port];
    }

    /**
     * Get tcp connections from /proc/net/tcp
     *
     * @return array
     */
    private function getProcTcpConnections() : array
    {
        $tcp = $this->readFileLines("$this->basePath/net/tcp");
        if (!empty($tcp)) {
            $first = true;
            $counter = 0;
            foreach ($tcp as $row) {
                if (!empty($row)) {
                    if ($first) {
                        $headers = explode(' ', preg_replace('!\s+!', ' ', trim($row)));
                        $first = false;
                    } else {
                        $values = explode(' ', preg_replace('!\s+!', ' ', trim($row)));
                        if (!empty($headers) and !empty($values)) {
                            $subcounter = 0;
                            foreach ($headers as $header) {
                                $results[$counter][$header] = $values[$subcounter];
                                $subcounter++;
                            }
                        }
                        $counter++;
                    }
                }
            }
        }

        return $results ?? [];
    }

    /**
     * Get network device statistics
     *
     * @param array|null $returnOnly Return only these columns.
     * @return array
     */
    public function networks(array $returnOnly = null) : array
    {

        $networks1 = $this->getProcNetworkDevices($returnOnly);

        if (!empty($returnOnly) and in_array('load', $returnOnly)) {
            sleep(1);
            $networks2 = $this->getProcNetworkDevices($returnOnly);
            foreach ($networks2 as $i => $network) {
                $networks2[$i]['load'] = $network['bytes'] - $networks1[$i]['bytes'];
                $networks2[$i]['load_out'] = $network['bytes_out'] - $networks1[$i]['bytes_out'];

            }

            return $networks2 ?? [];
        }

        return $networks1 ?? [];
    }

    /**
     * Get network device info from /proc/net/dev
     *
     * @param array|null $returnOnly Return only these columns.
     * @return array
     */
    private function getProcNetworkDevices($returnOnly) : array
    {
        $ethernet = $this->readFileLines("$this->basePath/net/dev");
        if (!empty($ethernet)) {
            unset($ethernet[0]);
            $first = true;
            $counter = 0;
            foreach ($ethernet as $row) {
                if (!empty($row)) {
                    if ($first) {
                        $headers = explode(' ', preg_replace('!\s+!', ' ', str_replace('|', ' ', trim($row))));
                        $first = false;
                        if (!empty($headers)) {
                            $newheaders = [];
                            foreach ($headers as $header) {
                                if (in_array($header, $newheaders)) {
                                    $newheaders[] = $header.'_out';
                                }
                                else {
                                    $newheaders[] = $header;
                                }
                            }
                        }
                    } else {
                        $values = explode(' ', preg_replace('!\s+!', ' ', trim($row)));
                        if (!empty($newheaders) and !empty($values)) {
                            $subcounter = 0;
                            foreach ($newheaders as $header) {
                                if (empty($returnOnly) or in_array($header, $returnOnly)) {
                                    $results[$counter][$header] = $values[$subcounter];
                                }
                                $subcounter++;
                            }
                        }
                        $counter++;
                    }
                }
            }
        }

        return $results ?? [];
    }
    /**
     * Parse the text file into an array based on newline
     *
     * @param string $filename
     * @return array
     */
    private function readFileLines(string $filename) : array
    {
        if (is_file($filename) and is_readable($filename)) {
            $file = @file_get_contents($filename);
            if (!empty($file)) {
                $results = explode(PHP_EOL, $file);
            }
        }

        return $results ?? [];
    }

    /**
     * Format bytes to kb, mb, gb, tb
     *
     * @param  integer $size
     * @param  integer $precision
     * @return integer
     */
    public static function formatBytes($size, $precision = 2)
    {
        if ($size > 0) {
            $size = (int) $size;
            $base = log($size) / log(1024);
            $suffixes = array(' bytes', ' KB', ' MB', ' GB', ' TB');

            return round(pow(1024, $base - floor($base)), $precision) . $suffixes[(int)floor($base)];
        } else {
            return $size;
        }
    }
}