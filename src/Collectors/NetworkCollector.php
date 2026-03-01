<?php

declare(strict_types=1);

namespace danielme85\Server\Collectors;

/**
 * Reads network interface statistics and TCP connections from /proc/net.
 */
class NetworkCollector extends AbstractCollector
{
    public function all(): array
    {
        return [
            'interfaces'      => $this->interfaces(),
            'tcp_connections' => $this->tcpConnections(),
        ];
    }

    /**
     * Return network interface statistics, optionally including a 1-second
     * load (bytes/s) calculation when 'load' is in $returnOnly.
     *
     * @param string[]|null $returnOnly Columns to include; null = all.
     */
    public function interfaces(?array $returnOnly = null): array
    {
        $sample1 = $this->parseNetDev($returnOnly);

        if (!empty($returnOnly) && in_array('load', $returnOnly, true)) {
            sleep(1);
            $sample2 = $this->parseNetDev($returnOnly);

            foreach ($sample2 as $ifName => $row) {
                if (!isset($sample1[$ifName])) {
                    continue;
                }
                $sample2[$ifName]['load']     = ($row['bytes']     ?? 0) - ($sample1[$ifName]['bytes']     ?? 0);
                $sample2[$ifName]['load_out'] = ($row['bytes_out'] ?? 0) - ($sample1[$ifName]['bytes_out'] ?? 0);
            }

            return $sample2;
        }

        return $sample1;
    }

    /**
     * Return a list of all TCP connections, optionally including localhost.
     */
    public function tcpConnections(bool $includeLocalhost = false): array
    {
        $raw     = $this->proc->parseColumnar('net/tcp');
        $results = [];

        foreach ($raw as $row) {
            $local  = $this->decodeAddress($row['local_address'] ?? '');
            $remote = $this->decodeAddress($row['rem_address']   ?? '');

            if ($local['ip'] === '0.0.0.0' || $remote['ip'] === '0.0.0.0') {
                continue;
            }
            if (!$includeLocalhost && ($local['ip'] === '127.0.0.1' || $remote['ip'] === '127.0.0.1')) {
                continue;
            }

            $results[] = [
                'local_ip'    => $local['ip'],
                'local_port'  => $local['port'],
                'remote_ip'   => $remote['ip'],
                'remote_port' => $remote['port'],
            ];
        }

        return $results;
    }

    /**
     * Return a count of TCP connections grouped by local IP:port.
     */
    public function tcpConnectionsSummarized(bool $includeLocalhost = false): array
    {
        $results = [];

        foreach ($this->tcpConnections($includeLocalhost) as $conn) {
            $key = $conn['local_ip'] . ':' . $conn['local_port'];
            if (!isset($results[$key])) {
                $results[$key] = [
                    'ip'          => $conn['local_ip'],
                    'port'        => $conn['local_port'],
                    'connections' => 0,
                ];
            }
            $results[$key]['connections']++;
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function parseNetDev(?array $returnOnly): array
    {
        $lines    = $this->proc->lines('net/dev');
        $results  = [];
        $headers  = [];
        $first    = true;

        // First line is a description banner — skip it
        array_shift($lines);

        foreach ($lines as $row) {
            if ($row === '') {
                continue;
            }
            $parts = preg_split('/\s+/', trim(str_replace('|', ' ', $row)));

            if ($first) {
                // Deduplicate duplicate column names (receive vs transmit side)
                foreach ($parts as $h) {
                    $headers[] = in_array($h, $headers, true) ? $h . '_out' : $h;
                }
                $first = false;
                continue;
            }

            $entry = [];
            // Use the interface name (face column, with trailing colon stripped) as key
            $ifName = rtrim($parts[0] ?? '', ':');
            foreach ($headers as $i => $header) {
                if (empty($returnOnly) || in_array($header, $returnOnly, true)) {
                    $entry[$header] = $parts[$i] ?? '';
                }
            }
            $results[$ifName] = $entry;
        }

        return $results;
    }

    /** @return array{ip: string, port: int} */
    private function decodeAddress(string $hex): array
    {
        // Linux /proc/net/tcp stores addresses as little-endian hex: AABBCCDD:PPPP
        [$addrHex, $portHex] = array_pad(explode(':', $hex), 2, '0');

        $ip = implode('.', array_map(
            fn(int $offset): int => hexdec(substr($addrHex, $offset, 2)),
            [6, 4, 2, 0]
        ));

        return ['ip' => $ip, 'port' => (int) hexdec($portHex)];
    }
}
