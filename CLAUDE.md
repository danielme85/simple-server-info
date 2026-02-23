# CLAUDE.md — simple-server-info

Guidelines for working with this codebase using Claude Code.

## Project overview

A PHP 8.1+ library that reads Linux server/system metrics (CPU, memory, disk,
network, processes, uptime) from the `/proc` virtual filesystem. No shell
commands are used; all data is read from `/proc` text files.

## Architecture

```
src/
├── Contracts/CollectorInterface.php   # Every collector must implement this
├── Collectors/AbstractCollector.php   # Base class: ProcReader injection + helpers
├── Collectors/CpuCollector.php
├── Collectors/DiskCollector.php
├── Collectors/MemoryCollector.php
├── Collectors/NetworkCollector.php
├── Collectors/ProcessCollector.php
├── Collectors/SystemCollector.php
├── Formatter.php                      # Stateless byte-formatting helper
├── Info.php                           # Public facade — delegates to collectors
└── ProcReader.php                     # Low-level /proc reader (no business logic)
```

## Key conventions

- **PHP 8.1+** — use typed properties, `readonly`, `match`, named arguments, union types.
- **`declare(strict_types=1)`** at the top of every PHP file.
- `ProcReader` handles all I/O. Collectors must not call `file_get_contents` or
  `scandir` directly — use `$this->proc->lines(...)`, `->parseColumnar(...)`,
  or `->pidList()`.
- `Formatter::bytes()` is the single source of truth for byte formatting.
  Do **not** duplicate formatting logic inline.
- `Info` is a **facade only** — no business logic belongs there. Add logic to
  the appropriate collector instead.
- `AbstractCollector::filterKeys()` should be used to honour `$returnOnly`
  parameters instead of manual `array_intersect_key` calls.
- Public API of `Info` must remain backward-compatible. If removing or renaming
  a method is necessary, deprecate it first.

## Adding a new collector

1. Create `src/Collectors/MyCollector.php` extending `AbstractCollector`.
2. Implement `CollectorInterface::all(): array`.
3. Inject `ProcReader` via the constructor (already provided by `AbstractCollector`).
4. Add a getter on `Info` (e.g. `public function myCollector(): MyCollector`).
5. Optionally add facade convenience methods on `Info`.

## Running tests

```bash
composer install
./vendor/bin/phpunit
```

Tests live in `tests/InfoTest.php`. Each test group maps to a feature area
(`@group cpu`, `@group memory`, etc.). Tests require a Linux environment with
a live `/proc` filesystem.

## Environment notes

- Tests must run on Linux with procfs available.
- Some tests invoke `sleep(1)` internally (CPU load, network load) — this is
  intentional and cannot be avoided without mocking the filesystem.
- `PrimeStress.php` in the project root is used by the CPU load test to
  generate load during measurement.

## Composer

- Minimum PHP: `8.1`
- Dev dependency: `phpunit/phpunit ^10`
- PSR-4 autoload root: `danielme85\Server\` → `src/`
