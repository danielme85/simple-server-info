# PHP Simple Server Info

[![GitHub](https://img.shields.io/github/license/mashape/apistatus.svg?style=flat-square)](https://github.com/danielme85/simple-server-info)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/danielme85/simple-server-info.svg?style=flat-square)](https://packagist.org/packages/danielme85/simple-server-info)
[![GitHub release](https://img.shields.io/github/release/danielme85/simple-server-info.svg?style=flat-square)](https://packagist.org/packages/danielme85/simple-server-info)
[![GitHub tag](https://img.shields.io/github/tag/danielme85/simple-server-info.svg?style=flat-square)](https://github.com/danielme85/simple-server-info)

A PHP 8.1+ library that reads server and system information directly from the Linux
[`/proc` virtual filesystem](https://en.wikipedia.org/wiki/Procfs).

No `exec`, `shell_exec`, or other shell commands are used — all data is read from `/proc` text
files, making this safe, portable, and easy to audit.

---

## Requirements

* Linux/Unix OS with [Procfs](https://en.wikipedia.org/wiki/Procfs) support (`/proc`).
* PHP 8.1 or later.

---

## Installation

```bash
composer require danielme85/simple-server-info
```

---

## Quick start

```php
use danielme85\Server\Info;

// Instantiate directly
$info = new Info();

// Or use the static factory for method chaining
$cpuLoad = Info::get()->cpuLoad(sampleSec: 1, rounding: 2);
```

---

## Architecture

The library is organised around small, focused **Collector** classes, each responsible for a
single concern. The `Info` class is a **facade** that delegates to the collectors while
preserving a clean, unified API.

```
src/
├── Contracts/
│   └── CollectorInterface.php   # Interface implemented by every collector
├── Collectors/
│   ├── AbstractCollector.php    # Shared parsing helpers
│   ├── CpuCollector.php         # CPU info & load
│   ├── DiskCollector.php        # Disk partitions & volumes
│   ├── MemoryCollector.php      # RAM & swap
│   ├── NetworkCollector.php     # Network interfaces & TCP connections
│   ├── ProcessCollector.php     # Process listing & CPU usage
│   └── SystemCollector.php      # Uptime & kernel version
├── Formatter.php                # Byte-formatting helper
├── Info.php                     # Public facade
└── ProcReader.php               # Low-level /proc file reader
```

Collectors can be used independently:

```php
use danielme85\Server\ProcReader;
use danielme85\Server\Collectors\CpuCollector;

$cpu = new CpuCollector(new ProcReader());
$load = $cpu->load(sampleSec: 1);
```

Or accessed through the facade:

```php
$load = Info::get()->cpu()->load();
```

---

## API Reference

### CPU

```php
// All cores, all fields
$cpuInfo = Info::get()->cpuInfo();

// Single core, specific fields
$core0 = Info::get()->cpuInfo(core: 0, returnonly: ['model_name', 'cpu_mhz', 'cache_size']);

// Load percentage per core (samples over $sampleSec seconds)
$cpuLoad = Info::get()->cpuLoad(sampleSec: 1, rounding: 2);
// Returns: ['cpu' => ['label' => 'CPU', 'load' => 12.5], 'cpu0' => [...], ...]
```

### Memory

```php
// Usage summary with formatted sizes (e.g. "512.00 MB")
$usage = Info::get()->memoryUsage();

// Raw byte values
$usageBytes = Info::get()->memoryUsage(formatSizes: false);

// Percentage load
$load = Info::get()->memoryLoad(rounding: 2);
// Returns: ['load' => 42.5, 'swap_load' => 0.0]

// Full /proc/meminfo dump (bytes)
$all = Info::get()->memoryInfo();
```

### Disk & Volumes

```php
// Block device information from /proc/partitions
$disks = Info::get()->diskInfo();

// Mounted volume usage (filtered by filesystem type)
$volumes = Info::get()->volumesInfo();

// Customise which filesystem types to include
$info = new Info(filesystemTypes: ['ext4', 'xfs']);
$volumes = $info->volumesInfo();
```

### Processes

```php
// All processes (stat + status combined)
$all = Info::get()->processes();

// Single process
$proc = Info::get()->process(pid: 1);

// Filtered: specific fields, stat only, running only
$running = Info::get()->processes(
    returnonly: ['pid', 'comm', 'state', 'vsize'],
    returntype: 'stat',
    runningonly: true
);

// Active or running processes with CPU usage
$active = Info::get()->processesActiveOrRunning(
    returnonly: ['comm', 'state', 'pid', 'cpu_usage'],
    returntype: 'stat'
);
```

### Network

```php
// Network interface statistics
$interfaces = Info::get()->networks();

// With per-second load calculation (adds a 1s sleep)
$withLoad = Info::get()->networks(returnOnly: ['face', 'bytes', 'bytes_out', 'load', 'load_out']);

// TCP connections
$connections = Info::get()->tcpConnections(includeLocalhost: false);

// Summarised by local IP:port
$summary = Info::get()->tcpConnectionsSummarized();
```

### System / Uptime

```php
$uptime = Info::get()->uptime();
// Returns:
// [
//   'current_unix' => 1700000000,
//   'uptime_unix'  => 86400,
//   'started_unix' => 1699913600,
//   'started'      => '2023-11-13 12:00:00',
//   'current'      => '2023-11-14 12:00:00',
//   'uptime'       => '1:00:00:00',
//   'uptime_text'  => '1 days, 0 hours, 0 minutes and 0 seconds',
// ]

$info = Info::get()->otherInfo();
// Returns: ['version' => '...', 'version_signature' => '...']
```

### Formatting helper

```php
use danielme85\Server\Formatter;

echo Formatter::bytes(1073741824); // "1.00 GB"

// Also available as a static method on Info for backward compatibility:
echo Info::formatBytes(1073741824);
```

---

## Extending

Implement `CollectorInterface` to create a custom collector and pass a `ProcReader` instance:

```php
use danielme85\Server\Contracts\CollectorInterface;
use danielme85\Server\Collectors\AbstractCollector;

class LoadAvgCollector extends AbstractCollector implements CollectorInterface
{
    public function all(): array
    {
        $lines = $this->proc->lines('loadavg');
        $parts = explode(' ', $lines[0] ?? '');

        return [
            '1min'  => (float) ($parts[0] ?? 0),
            '5min'  => (float) ($parts[1] ?? 0),
            '15min' => (float) ($parts[2] ?? 0),
        ];
    }
}
```

---

## Running Tests

```bash
composer install
./vendor/bin/phpunit
```
