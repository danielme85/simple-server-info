<?php

declare(strict_types=1);

namespace danielme85\Server\Collectors;

use danielme85\Server\Formatter;

/**
 * Reads disk partition and mount/volume information from /proc.
 */
class DiskCollector extends AbstractCollector
{
    /** @var string[] File-system types to include in volumesInfo(). */
    private array $filesystemTypes;

    /** @param string[] $filesystemTypes */
    public function __construct(\danielme85\Server\ProcReader $proc, array $filesystemTypes = [])
    {
        parent::__construct($proc);
        $this->filesystemTypes = $filesystemTypes ?: ['ext', 'ext2', 'ext3', 'ext4', 'fat32', 'ntfs', 'vboxsf'];
    }

    public function all(): array
    {
        return [
            'disks'   => $this->diskInfo(),
            'volumes' => $this->volumesInfo(),
        ];
    }

    /** @return string[] */
    public function filesystemTypes(): array
    {
        return $this->filesystemTypes;
    }

    /**
     * Return block-device information from /proc/partitions.
     */
    public function diskInfo(): array
    {
        $results = [];

        foreach ($this->proc->parseColumnar('partitions') as $row) {
            if (empty($row)) {
                continue;
            }
            $bytes = (int) $row['#blocks'] * 1024;
            $results[$row['name']] = [
                'id'       => $row['major'] . ':' . $row['minor'],
                'blocks'   => $row['#blocks'],
                'bytes'    => $bytes,
                'formated' => Formatter::bytes($bytes),
            ];
        }

        return $results;
    }

    /**
     * Return usage information for mounted volumes of the configured types.
     */
    public function volumesInfo(): array
    {
        $mounts  = $this->parseMounts();
        $results = [];

        foreach ($mounts as $mount) {
            if (!in_array($mount['file_system'], $this->filesystemTypes, true)) {
                continue;
            }

            $total = (int) disk_total_space($mount['mount']);
            $free  = (int) disk_free_space($mount['mount']);
            $used  = $total - $free;

            $mount['total_space_bytes'] = $total;
            $mount['total_space']       = Formatter::bytes($total);
            $mount['free_space_bytes']  = $free;
            $mount['free_space']        = Formatter::bytes($free);
            $mount['used_space_bytes']  = $used;
            $mount['used_space']        = Formatter::bytes($used);
            $mount['used_percent']      = round($total > 0 ? ($used / $total) * 100 : 0.0, 2);

            $results[] = $mount;
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return array<int, array{mount: string, disk: string, file_system: string}> */
    private function parseMounts(): array
    {
        $lines   = $this->proc->lines('mounts');
        $results = [];

        foreach ($lines as $row) {
            if ($row === '') {
                continue;
            }
            $parts = preg_split('/\s+/', trim($row));
            $results[] = [
                'disk'        => $parts[0] ?? '',
                'mount'       => $parts[1] ?? '',
                'file_system' => $parts[2] ?? '',
            ];
        }

        return $results;
    }
}
