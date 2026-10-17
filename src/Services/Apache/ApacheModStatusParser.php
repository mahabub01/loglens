<?php

namespace MehrabHossain\LogLens\Services\Apache;

use InvalidArgumentException;

class ApacheModStatusParser
{
    /**
     * Scoreboard status character mapping.
     */
    public const SCOREBOARD_MAP = [
        '_' => 'waiting',
        'S' => 'starting',
        'R' => 'reading',
        'W' => 'writing',
        'K' => 'keepalive',
        'D' => 'dns',
        'C' => 'closing',
        'L' => 'logging',
        'G' => 'graceful',
        'I' => 'idle_cleanup',
        '.' => 'open_slot',
    ];

    /**
     * Parse raw mod_status machine-readable response text.
     *
     * @param  string  $rawContent
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $rawContent): array
    {
        $trimmed = trim($rawContent);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Empty response received from Apache mod_status.');
        }

        // Guard against HTML error pages
        if (str_starts_with(strtolower($trimmed), '<!doctype') || str_starts_with(strtolower($trimmed), '<html')) {
            throw new InvalidArgumentException('Received HTML instead of machine-readable mod_status?auto text.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $trimmed);
        $kv = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $val] = explode(':', $line, 2);
            $normalizedKey = strtolower(trim($key));
            $kv[$normalizedKey] = trim($val);
        }

        // Validate that essential fields exist (accounting for version variations)
        $hasAccesses = isset($kv['total accesses']);
        $hasScoreboard = isset($kv['scoreboard']);
        $hasUptime = isset($kv['uptime']);

        if (! $hasAccesses && ! $hasScoreboard && ! $hasUptime) {
            throw new InvalidArgumentException('Response does not contain valid Apache mod_status fields.');
        }

        // 1. Total Accesses
        $totalAccesses = isset($kv['total accesses']) ? (int) $kv['total accesses'] : null;

        // 2. Total Bytes / kBytes
        $totalBytes = null;
        if (isset($kv['total kbytes'])) {
            $totalBytes = (int) round(((float) $kv['total kbytes']) * 1024);
        } elseif (isset($kv['total bytes'])) {
            $totalBytes = (int) $kv['total bytes'];
        }

        // 3. Uptime
        $uptime = isset($kv['uptime']) ? (int) $kv['uptime'] : null;

        // 4. Server-reported lifetime averages
        $serverReqPerSec = isset($kv['reqpersec']) ? (float) $kv['reqpersec'] : null;
        $serverBytesPerSec = isset($kv['bytespersec']) ? (float) $kv['bytespersec'] : null;
        $serverBytesPerReq = isset($kv['bytesperreq']) ? (float) $kv['bytesperreq'] : null;

        // 5. Worker counts
        $busyWorkers = isset($kv['busyworkers'])
            ? (int) $kv['busyworkers']
            : (isset($kv['busyservers']) ? (int) $kv['busyservers'] : null);

        $idleWorkers = isset($kv['idleworkers'])
            ? (int) $kv['idleworkers']
            : (isset($kv['idleservers']) ? (int) $kv['idleservers'] : null);

        // 6. Scoreboard parsing & tallying
        $scoreboardRaw = $kv['scoreboard'] ?? '';
        $scoreboardBreakdown = $this->parseScoreboard($scoreboardRaw);

        // If busy/idle workers weren't directly provided, infer from scoreboard
        if ($busyWorkers === null && $scoreboardRaw !== '') {
            $busyWorkers = strlen($scoreboardRaw) - ($scoreboardBreakdown['waiting'] + $scoreboardBreakdown['open_slot']);
        }
        if ($idleWorkers === null && $scoreboardRaw !== '') {
            $idleWorkers = $scoreboardBreakdown['waiting'];
        }

        return [
            'server_version' => $kv['serverversion'] ?? ($kv['server version'] ?? 'Apache'),
            'uptime_seconds' => $uptime,
            'total_accesses' => $totalAccesses,
            'total_bytes' => $totalBytes,
            'server_req_per_sec' => $serverReqPerSec,
            'server_bytes_per_sec' => $serverBytesPerSec,
            'server_bytes_per_req' => $serverBytesPerReq,
            'busy_workers' => $busyWorkers,
            'idle_workers' => $idleWorkers,
            'scoreboard_raw' => $scoreboardRaw,
            'scoreboard_breakdown' => $scoreboardBreakdown,
            'raw_fields' => $kv,
        ];
    }

    /**
     * Tally occurrences of each scoreboard state character.
     *
     * @param  string  $scoreboard
     * @return array<string, int>
     */
    public function parseScoreboard(string $scoreboard): array
    {
        $breakdown = [
            'waiting' => 0,       // '_'
            'starting' => 0,      // 'S'
            'reading' => 0,       // 'R'
            'writing' => 0,       // 'W'
            'keepalive' => 0,     // 'K'
            'dns' => 0,           // 'D'
            'closing' => 0,       // 'C'
            'logging' => 0,       // 'L'
            'graceful' => 0,      // 'G'
            'idle_cleanup' => 0,  // 'I'
            'open_slot' => 0,     // '.'
        ];

        $len = strlen($scoreboard);
        for ($i = 0; $i < $len; $i++) {
            $char = $scoreboard[$i];
            if (isset(self::SCOREBOARD_MAP[$char])) {
                $breakdown[self::SCOREBOARD_MAP[$char]]++;
            }
        }

        return $breakdown;
    }
}
