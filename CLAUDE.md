# CLAUDE.md — simple-server-info

Guidelines for working with this codebase using Claude Code.

## Project overview

A PHP 8.1+ library that reads Linux server/system metrics (CPU, memory, disk,
network, processes, uptime, GPU) from the `/proc` and `/sys` virtual filesystems.
No shell commands are used; all data is read from virtual filesystem files.

## Architecture

```
src/
├── Contracts/CollectorInterface.php   # Every collector must implement this
├── Collectors/AbstractCollector.php   # Base class: ProcReader/SysReader injection + helpers
├── Collectors/CpuCollector.php
├── Collectors/DiskCollector.php
├── Collectors/GpuCollector.php        # GPU info & resource usage via /sys/class/drm/
├── Collectors/MemoryCollector.php
├── Collectors/NetworkCollector.php
├── Collectors/ProcessCollector.php
├── Collectors/SystemCollector.php
├── Formatter.php                      # Stateless byte-formatting helper
├── Info.php                           # Public facade — delegates to collectors
├── ProcReader.php                     # Low-level /proc reader (no business logic)
└── SysReader.php                      # Low-level /sys reader (no business logic)
```

## Key conventions

- **PHP 8.1+** — use typed properties, `readonly`, `match`, named arguments, union types.
- **`declare(strict_types=1)`** at the top of every PHP file.
- `ProcReader` handles all `/proc` I/O. `SysReader` handles all `/sys` I/O.
  Collectors must not call `file_get_contents` or `scandir` directly.
  Use `$this->proc->lines(...)`, `->parseColumnar(...)`, `->pidList()` for procfs,
  and `$this->sys->read(...)`, `->readInt(...)`, `->listDir(...)` for sysfs.
- `Formatter::bytes()` is the single source of truth for byte formatting.
  Do **not** duplicate formatting logic inline.
- `Info` is a **facade only** — no business logic belongs there. Add logic to
  the appropriate collector instead.
- `AbstractCollector::filterKeys()` should be used to honour `$returnOnly`
  parameters instead of manual `array_intersect_key` calls.
- Public API of `Info` must remain backward-compatible. If removing or renaming
  a method is necessary, deprecate it first.
- Collector accessor methods on `Info` use **camelCase** (e.g. `processCollector()`,
  not `processes_collector()`).
- Network interface results are keyed by **interface name** (e.g. `'lo'`, `'eth0'`),
  not by numeric index.

## Adding a new collector

1. Create `src/Collectors/MyCollector.php` extending `AbstractCollector`.
2. Implement `CollectorInterface::all(): array`.
3. `ProcReader` is always available as `$this->proc`. If your collector needs
   sysfs access, pass a `SysReader` as the second constructor argument
   (`parent::__construct($proc, $sys)`) — it will be available as `$this->sys`.
4. Add a getter on `Info` (e.g. `public function myCollector(): MyCollector`).
5. Optionally add facade convenience methods on `Info`.
6. Add tests in `tests/InfoTest.php` with an appropriate `@group` annotation.

## Running tests

```bash
composer install
./vendor/bin/phpunit
```

Tests live in `tests/InfoTest.php`. Each test group maps to a feature area
(`@group cpu`, `@group memory`, `@group gpu`, etc.). Tests require a Linux
environment with a live `/proc` filesystem.

## CI

GitHub Actions runs PHPUnit on PHP 8.1, 8.2, 8.3, and 8.4 via
`.github/workflows/tests.yml`. There is no Travis CI config — the old
`.travis.yml` has been removed.

## Environment notes

- Tests must run on Linux with procfs available.
- Some tests invoke `sleep(1)` internally (CPU load, process CPU usage) —
  this is intentional and cannot be avoided without mocking the filesystem.
- `PrimeStress.php` in the project root is used by the CPU load test to
  generate load during measurement. The test references it via `__DIR__`
  for a reliable absolute path.
- Tests use the `processor` key from `/proc/cpuinfo` (not `model_name`)
  because it is present on all CPU architectures (x86, ARM, etc.).

## Composer

- Minimum PHP: `8.1`
- Dev dependency: `phpunit/phpunit >=10`
- PSR-4 autoload root: `danielme85\Server\` → `src/`
