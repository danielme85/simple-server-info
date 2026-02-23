<?php

declare(strict_types=1);

namespace danielme85\Server\Collectors;

/**
 * Reads OS-level system information: uptime and kernel version.
 */
class SystemCollector extends AbstractCollector
{
    public function all(): array
    {
        return [
            'uptime'     => $this->uptime(),
            'other_info' => $this->otherInfo(),
        ];
    }

    /**
     * Return uptime data, optionally including human-readable strings.
     */
    public function uptime(bool $formatted = true): array
    {
        $lines = $this->proc->lines('uptime');
        if (empty($lines[0])) {
            return [];
        }

        [$uptimeSecs] = explode(' ', $lines[0]);
        $uptimeSecs   = (int) $uptimeSecs;
        $now          = time();
        $startedAt    = $now - $uptimeSecs;

        $result = [
            'current_unix' => $now,
            'uptime_unix'  => $uptimeSecs,
            'started_unix' => $startedAt,
        ];

        if ($formatted) {
            $started  = date('Y-m-d H:i:s', $startedAt);
            $current  = date('Y-m-d H:i:s', $now);
            $interval = (new \DateTime($started))->diff(new \DateTime($current));

            $result['started']      = $started;
            $result['current']      = $current;
            $result['uptime']       = $interval->format('%a:%H:%I:%S');
            $result['uptime_text']  = $interval->format('%a days, %h hours, %i minutes and %s seconds');
        }

        return $result;
    }

    /**
     * Return kernel version strings.
     */
    public function otherInfo(): array
    {
        $version          = $this->proc->lines('version');
        $versionSignature = $this->proc->lines('version_signature');

        return [
            'version'           => $version[0] ?? null,
            'version_signature' => $versionSignature[0] ?? null,
        ];
    }
}
