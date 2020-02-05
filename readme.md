# PHP Simple Server Info
[![GitHub](https://img.shields.io/github/license/mashape/apistatus.svg?style=flat-square)](https://github.com/danielme85/simple-server-info)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/danielme85/simple-server-info.svg?style=flat-square)](https://packagist.org/packages/danielme85/simple-server-info)
[![GitHub release](https://img.shields.io/github/release/danielme85/simple-server-info.svg?style=flat-square)](https://packagist.org/packages/danielme85/simple-server-info)
[![GitHub tag](https://img.shields.io/github/tag/danielme85/simple-server-info.svg?style=flat-square)](https://github.com/danielme85/simple-server-info)
[![Travis (.org)](https://img.shields.io/travis/danielme85/simple-server-info.svg?style=flat-square)](https://travis-ci.org/danielme85/simple-server-info)
[![Codecov](https://img.shields.io/codecov/c/github/danielme85/simple-server-info.svg?style=flat-square)](https://codecov.io/gh/danielme85/simple-server-info)

A simple PHP 7.1+ class to provide system information about your unix/linux server/system.

Get CPU information and load. Memory and storage/volume usage and information. Made with efficiency and simplicity in mind. 
Information is read from the virtual filesystem "/proc" on Unix systems, as such Procfs is required. There is no usage of excec 
or other shell commands/hacks to get to the underlying system information. All information is read from the virtual /proc filesystem.


### Requirements
* Unix/Linux OS supporting the [/proc virtual file system](https://en.wikipedia.org/wiki/Procfs).
* PHP 7.1 or later.
 
### Installation
```
require danielme85/simple-server-info
```
Include vendor/autoload.php or however else you prefer to include stuff to your project/framework.
 
### Usage examples
 ```php
use danielme85\Server\Info;

$info = new Info();

$cpuInfo = $info->cpuInfo();
$cpuUsage = $info->cpuLoad($sampleSec = 1, $rounding = 2);

 ```
Static shortcut 
```php
$memoryInfo = Info::get()->memoryInfo();
```

### Currently supported info
The following information is supported.

#### CPU
The method "cpuInfo" returns an array with information about the CPU. This is per core, though most of the information
is duplicated as the cores usually shares the same parent information. The array is organized and indexed with the core_id.
To limit the information return you can specify the core and/or an array with the information you want.
The method "cpuLoad" returns an array with the percentage load per core based on samples with a 
set sec (default 1 sec) pause in between.
```php
$cpuinfo = Info::get()->cpuInfo();
$filteredCpuInfo = Info::get()->cpuInfo($core = null, ['processor', 'model_name', 'cpu_mhz', 'cache_size']);
$cpuLoad = Info::get()->cpuLoad($sampleSec = 1, $rounding = 2);
```

#### Memory
The method "memoryUsage" returns information about the current memory usage in a byte format.
The method "memoryLoad" returns the percentage load/usage. 
```php
$memoryUsage = Info::get()->memoryUsage();
$memoryLoad = Info::get()->memoryLoad();
```

#### Filesystem/Volumes
The method "volumesInfo" returns information about the mounted file systems/volumes.
```php
$volumes = Info::get()->volumesInfo(),
```

#### Processes
The methods "processes" and "process" returns information about processes currently present in the /proc system on the system.
There are results available from both the 'stat' and 'status' virtual system files. 
You can filter the results by passing an array of wanted columns, specify status/stat only and lastly set $runningonly = true/false. 
```php
$allprocs = Info::get()->processes();
$filteredprocs = Info::get()->processes(['name', 'state', 'pid', 'ppid', 'vmpeak', 'vmsize', 'threads'], 'status', true);
$spesifficproc = Info::get()->process($pid);
```

#### System uptime
```php
$uptime => Info::get()->uptime(),
```