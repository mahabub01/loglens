<?php

namespace MehrabHossain\LogLens\Services;

use Closure;
use MehrabHossain\LogLens\Contracts\SystemMetricsCollectorInterface;
use MehrabHossain\LogLens\LogLens;
use MehrabHossain\LogLens\Support\PlatformDetector;

class SystemMetricsCollector implements SystemMetricsCollectorInterface
{
    /**
     * Optional mock reader for reading /proc or /sys files during testing.
     */
    protected static ?Closure $fileReader = null;

    protected ?array $previousCpuStat = null;

    public function __construct(
        protected PlatformDetector $platformDetector
    ) {
    }

    /**
     * Set a custom file reader callback for testing.
     */
    public static function setFileReader(?Closure $reader): void
    {
        static::$fileReader = $reader;
    }

    /**
     * Collect the current system metrics snapshot.
     */
    public function collect(): array
    {
        $platform = $this->platformDetector->detect();

        return [
            'timestamp' => microtime(true),
            'platform' => $platform,
            'load_average' => $this->collectLoadAverage($platform),
            'cpu' => $this->collectCpuMetrics($platform),
            'memory' => $this->collectMemoryMetrics($platform),
            'swap' => $this->collectSwapMetrics($platform),
            'disk' => $this->collectDiskMetrics($platform),
            'log_directory' => $this->collectLogDirectoryMetrics(),
            'network' => $this->collectNetworkMetrics($platform),
        ];
    }

    /**
     * Get the detected platform and environment details.
     */
    public function getPlatform(): array
    {
        return $this->platformDetector->detect();
    }

    /**
     * Determine if system resource monitoring is enabled.
     */
    public function isEnabled(): bool
    {
        return LogLens::isEnabled() && (bool) config('loglens.system.enabled', true);
    }

    /**
     * Collect system load averages.
     */
    protected function collectLoadAverage(array $platform): ?array
    {
        if (! empty($platform['can_sys_load']) && function_exists('sys_getloadavg')) {
            $load = @sys_getloadavg();
            if (is_array($load) && count($load) >= 3) {
                return [
                    '1m' => round($load[0], 2),
                    '5m' => round($load[1], 2),
                    '15m' => round($load[2], 2),
                ];
            }
        }

        // On Linux, try reading /proc/loadavg if function is disabled
        if ($platform['is_linux']) {
            $content = $this->readFile('/proc/loadavg');
            if ($content) {
                $parts = explode(' ', trim($content));
                if (count($parts) >= 3) {
                    return [
                        '1m' => round((float) $parts[0], 2),
                        '5m' => round((float) $parts[1], 2),
                        '15m' => round((float) $parts[2], 2),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Collect CPU utilization metrics.
     */
    protected function collectCpuMetrics(array $platform): ?array
    {
        if (! $platform['is_linux']) {
            return null;
        }

        $content = $this->readFile('/proc/stat');
        if (! $content) {
            return null;
        }

        $lines = explode("\n", $content);
        $cpuLine = null;
        foreach ($lines as $line) {
            if (str_starts_with($line, 'cpu ')) {
                $cpuLine = $line;
                break;
            }
        }

        if (! $cpuLine) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($cpuLine));
        // Format: cpu user nice system idle iowait irq softirq steal guest
        if (count($parts) < 5) {
            return null;
        }

        $user = (int) $parts[1];
        $nice = (int) $parts[2];
        $system = (int) $parts[3];
        $idle = (int) $parts[4];
        $iowait = isset($parts[5]) ? (int) $parts[5] : 0;

        $total = $user + $nice + $system + $idle + $iowait;
        $active = $user + $nice + $system;

        $utilizationPercent = null;
        if ($this->previousCpuStat !== null) {
            $totalDelta = $total - $this->previousCpuStat['total'];
            $activeDelta = $active - $this->previousCpuStat['active'];

            if ($totalDelta > 0) {
                $utilizationPercent = round(($activeDelta / $totalDelta) * 100, 1);
            }
        }

        $this->previousCpuStat = [
            'total' => $total,
            'active' => $active,
        ];

        return [
            'utilization_percent' => $utilizationPercent,
            'is_available' => true,
        ];
    }

    /**
     * Collect memory utilization (Host or Container cgroups).
     */
    protected function collectMemoryMetrics(array $platform): array
    {
        $processUsage = memory_get_usage(true);
        $processPeak = memory_get_peak_usage(true);

        // 1. Container cgroups v2 check
        if ($platform['is_docker'] && $platform['cgroup_version'] === 2) {
            $maxStr = trim((string) $this->readFile('/sys/fs/cgroup/memory.max'));
            $currentStr = trim((string) $this->readFile('/sys/fs/cgroup/memory.current'));

            if (is_numeric($maxStr) && is_numeric($currentStr)) {
                $total = (float) $maxStr;
                $used = (float) $currentStr;
                $free = max(0, $total - $used);

                return [
                    'total_bytes' => (int) $total,
                    'used_bytes' => (int) $used,
                    'free_bytes' => (int) $free,
                    'used_percent' => round(($used / $total) * 100, 1),
                    'process_usage_bytes' => $processUsage,
                    'process_peak_bytes' => $processPeak,
                    'source' => 'cgroups_v2',
                    'is_container' => true,
                    'is_available' => true,
                ];
            }
        }

        // 2. Container cgroups v1 check
        if ($platform['is_docker'] && $platform['cgroup_version'] === 1) {
            $limitStr = trim((string) $this->readFile('/sys/fs/cgroup/memory/memory.limit_in_bytes'));
            $usageStr = trim((string) $this->readFile('/sys/fs/cgroup/memory/memory.usage_in_bytes'));

            if (is_numeric($limitStr) && is_numeric($usageStr) && (float) $limitStr < 9000000000000000000) {
                $total = (float) $limitStr;
                $used = (float) $usageStr;
                $free = max(0, $total - $used);

                return [
                    'total_bytes' => (int) $total,
                    'used_bytes' => (int) $used,
                    'free_bytes' => (int) $free,
                    'used_percent' => round(($used / $total) * 100, 1),
                    'process_usage_bytes' => $processUsage,
                    'process_peak_bytes' => $processPeak,
                    'source' => 'cgroups_v1',
                    'is_container' => true,
                    'is_available' => true,
                ];
            }
        }

        // 3. Linux /proc/meminfo host check
        if ($platform['is_linux']) {
            $meminfo = $this->parseMeminfo();
            if ($meminfo && isset($meminfo['MemTotal'])) {
                $total = $meminfo['MemTotal'] * 1024;
                $available = isset($meminfo['MemAvailable'])
                    ? $meminfo['MemAvailable'] * 1024
                    : (($meminfo['MemFree'] ?? 0) + ($meminfo['Buffers'] ?? 0) + ($meminfo['Cached'] ?? 0)) * 1024;

                $used = max(0, $total - $available);
                $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0.0;

                return [
                    'total_bytes' => (int) $total,
                    'used_bytes' => (int) $used,
                    'free_bytes' => (int) $available,
                    'used_percent' => $percent,
                    'process_usage_bytes' => $processUsage,
                    'process_peak_bytes' => $processPeak,
                    'source' => 'proc_meminfo',
                    'is_container' => $platform['is_docker'],
                    'is_available' => true,
                ];
            }
        }

        // 4. Fallback for Windows / macOS / unsupported host environments
        return [
            'total_bytes' => null,
            'used_bytes' => null,
            'free_bytes' => null,
            'used_percent' => null,
            'process_usage_bytes' => $processUsage,
            'process_peak_bytes' => $processPeak,
            'source' => 'php_process',
            'is_container' => false,
            'is_available' => false,
        ];
    }

    /**
     * Collect swap space metrics.
     */
    protected function collectSwapMetrics(array $platform): array
    {
        if ($platform['is_linux']) {
            $meminfo = $this->parseMeminfo();
            if ($meminfo && isset($meminfo['SwapTotal']) && $meminfo['SwapTotal'] > 0) {
                $total = $meminfo['SwapTotal'] * 1024;
                $free = ($meminfo['SwapFree'] ?? 0) * 1024;
                $used = max(0, $total - $free);

                return [
                    'total_bytes' => (int) $total,
                    'used_bytes' => (int) $used,
                    'free_bytes' => (int) $free,
                    'used_percent' => round(($used / $total) * 100, 1),
                    'is_available' => true,
                ];
            }
        }

        return [
            'total_bytes' => null,
            'used_bytes' => null,
            'free_bytes' => null,
            'used_percent' => null,
            'is_available' => false,
        ];
    }

    /**
     * Collect disk utilization for root and application paths.
     */
    protected function collectDiskMetrics(array $platform): ?array
    {
        $path = $platform['is_windows'] ? 'C:' : '/';

        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false || $total <= 0) {
            return null;
        }

        $used = $total - $free;

        return [
            'path' => $path,
            'total_bytes' => (int) $total,
            'free_bytes' => (int) $free,
            'used_bytes' => (int) $used,
            'used_percent' => round(($used / $total) * 100, 1),
            'free_percent' => round(($free / $total) * 100, 1),
            'is_available' => true,
        ];
    }

    /**
     * Collect total size and count of application log files.
     */
    protected function collectLogDirectoryMetrics(): array
    {
        $paths = config('loglens.logs.paths', [storage_path('logs')]);
        $totalBytes = 0;
        $fileCount = 0;

        foreach ($paths as $dir) {
            if (! @is_dir($dir)) {
                continue;
            }

            $files = @scandir($dir);
            if (! is_array($files)) {
                continue;
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || ! str_ends_with($file, '.log')) {
                    continue;
                }

                $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                $size = @filesize($filePath);
                if ($size !== false) {
                    $totalBytes += $size;
                    $fileCount++;
                }
            }
        }

        return [
            'total_bytes' => $totalBytes,
            'file_count' => $fileCount,
        ];
    }

    /**
     * Collect network traffic counters.
     */
    protected function collectNetworkMetrics(array $platform): ?array
    {
        if (! $platform['is_linux']) {
            return null;
        }

        $content = $this->readFile('/proc/net/dev');
        if (! $content) {
            return null;
        }

        $lines = explode("\n", $content);
        $rxTotal = 0;
        $txTotal = 0;
        $matchedInterfaces = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (! str_contains($line, ':') || str_starts_with($line, 'lo:')) {
                continue;
            }

            [$interface, $stats] = explode(':', $line, 2);
            $cols = preg_split('/\s+/', trim($stats));
            if (count($cols) >= 9) {
                $rxTotal += (int) $cols[0];
                $txTotal += (int) $cols[8];
                $matchedInterfaces++;
            }
        }

        if ($matchedInterfaces === 0) {
            return null;
        }

        return [
            'rx_bytes' => $rxTotal,
            'tx_bytes' => $txTotal,
            'is_available' => true,
        ];
    }

    /**
     * Parse /proc/meminfo into an associative array.
     *
     * @return array<string, int>|null
     */
    protected function parseMeminfo(): ?array
    {
        $content = $this->readFile('/proc/meminfo');
        if (! $content) {
            return null;
        }

        $result = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $val] = explode(':', $line, 2);
            $val = trim($val);
            if (preg_match('/^(\d+)/', $val, $matches)) {
                $result[trim($key)] = (int) $matches[1];
            }
        }

        return $result;
    }

    /**
     * Safely read file contents using the test hook or standard file reading.
     */
    protected function readFile(string $path): ?string
    {
        if (static::$fileReader !== null) {
            return (static::$fileReader)($path);
        }

        if (! @file_exists($path) || ! @is_readable($path)) {
            return null;
        }

        return @file_get_contents($path) ?: null;
    }
}
