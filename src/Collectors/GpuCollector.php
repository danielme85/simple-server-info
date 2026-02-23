<?php

declare(strict_types=1);

namespace danielme85\Server\Collectors;

use danielme85\Server\Formatter;
use danielme85\Server\ProcReader;
use danielme85\Server\SysReader;

/**
 * Reads GPU hardware information and resource usage from /sys/class/drm/.
 *
 * Supports AMD and NVIDIA (open kernel module) GPUs.
 * Metrics available depend on the driver; absent values are omitted from output.
 *
 * Key sysfs paths used:
 *   /sys/class/drm/card{N}/device/vendor          — PCI vendor ID
 *   /sys/class/drm/card{N}/device/device          — PCI device ID
 *   /sys/class/drm/card{N}/device/mem_info_vram_total  — VRAM total bytes (AMD/NVIDIA open)
 *   /sys/class/drm/card{N}/device/mem_info_vram_used   — VRAM used bytes  (AMD/NVIDIA open)
 *   /sys/class/drm/card{N}/device/gpu_busy_percent     — utilisation %    (AMD/NVIDIA open)
 *   /sys/class/drm/card{N}/device/hwmon/hwmon{N}/temp1_input — temp (millidegrees)
 */
class GpuCollector extends AbstractCollector
{
    private const VENDOR_MAP = [
        '0x1002' => 'AMD',
        '0x10de' => 'NVIDIA',
        '0x8086' => 'Intel',
    ];

    public function __construct(ProcReader $proc, SysReader $sys)
    {
        parent::__construct($proc, $sys);
    }

    public function all(): array
    {
        return $this->gpus();
    }

    /**
     * Return an associative array of GPU info keyed by card name (e.g. 'card0').
     */
    public function gpus(): array
    {
        $cards = array_filter(
            $this->sys->listDir('class/drm'),
            fn($entry) => (bool) preg_match('/^card\d+$/', $entry)
        );

        $results = [];
        foreach ($cards as $card) {
            $results[$card] = $this->cardInfo($card);
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function cardInfo(string $card): array
    {
        $base = "class/drm/$card/device";

        $vendorHex = $this->sys->read("$base/vendor");
        $vendor    = self::VENDOR_MAP[strtolower($vendorHex ?? '')] ?? $vendorHex ?? 'Unknown';

        $info = [
            'vendor'    => $vendor,
            'vendor_id' => $vendorHex,
            'device_id' => $this->sys->read("$base/device"),
        ];

        $this->addVramInfo($info, $base);
        $this->addUtilisation($info, $base);
        $this->addTemperature($info, $base);

        return $info;
    }

    private function addVramInfo(array &$info, string $base): void
    {
        $total = $this->sys->readInt("$base/mem_info_vram_total");
        $used  = $this->sys->readInt("$base/mem_info_vram_used");

        if ($total !== null) {
            $info['vram_total']        = $total;
            $info['vram_total_format'] = Formatter::bytes($total);
        }

        if ($used !== null) {
            $info['vram_used']        = $used;
            $info['vram_used_format'] = Formatter::bytes($used);
        }

        if ($total !== null && $used !== null) {
            $info['vram_load'] = round($total > 0 ? ($used / $total) * 100 : 0.0, 2);
        }
    }

    private function addUtilisation(array &$info, string $base): void
    {
        $busy = $this->sys->readInt("$base/gpu_busy_percent");

        if ($busy !== null) {
            $info['gpu_busy_percent'] = $busy;
        }
    }

    private function addTemperature(array &$info, string $base): void
    {
        $hwmons = array_filter(
            $this->sys->listDir("$base/hwmon"),
            fn($e) => str_starts_with($e, 'hwmon')
        );

        foreach ($hwmons as $hwmon) {
            // temp1_input is millidegrees Celsius
            $millideg = $this->sys->readInt("$base/hwmon/$hwmon/temp1_input");

            if ($millideg !== null) {
                $info['temperature_celsius'] = round($millideg / 1000, 1);
                return;
            }
        }
    }
}
