<?php

namespace MehrabHossain\LogLens\Support;

class PlatformDetector
{
    protected static ?array $mockData = null;

    /**
     * Set mock platform data for testing.
     *
     * @param  array<string, mixed>|null  $mock
     */
    public static function setMockData(?array $mock): void
    {
        static::$mockData = $mock;
    }

    /**
     * Detect the current runtime and operating system environment.
     *
     * @return array<string, mixed>
     */
    public function detect(): array
    {
        if (static::$mockData !== null) {
            return static::$mockData;
        }

        $os = PHP_OS_FAMILY;
        $isLinux = ($os === 'Linux');
        $isWindows = ($os === 'Windows');
        $isMac = ($os === 'Darwin');

        $isDocker = $this->detectDocker();
        $cgroupVersion = $isLinux ? $this->detectCgroupVersion() : null;

        $disabledFunctions = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
        $canSysLoad = function_exists('sys_getloadavg') && ! in_array('sys_getloadavg', $disabledFunctions, true);
        $canReadProc = $isLinux && @is_dir('/proc');

        return [
            'os' => $os,
            'is_linux' => $isLinux,
            'is_windows' => $isWindows,
            'is_mac' => $isMac,
            'is_docker' => $isDocker,
            'cgroup_version' => $cgroupVersion,
            'php_version' => PHP_VERSION,
            'hostname' => gethostname() ?: 'unknown',
            'can_sys_load' => $canSysLoad,
            'can_read_proc' => $canReadProc,
            'disabled_functions' => $disabledFunctions,
        ];
    }

    /**
     * Detect if running inside a Docker or OCI container.
     */
    protected function detectDocker(): bool
    {
        if (@file_exists('/.dockerenv') || @file_exists('/run/.containerenv')) {
            return true;
        }

        if (@file_exists('/proc/1/cgroup')) {
            $content = @file_get_contents('/proc/1/cgroup');
            if ($content && (str_contains($content, 'docker') || str_contains($content, 'containerd'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect cgroups version on Linux.
     */
    protected function detectCgroupVersion(): ?int
    {
        if (@file_exists('/sys/fs/cgroup/cgroup.controllers')) {
            return 2;
        }

        if (@is_dir('/sys/fs/cgroup/memory')) {
            return 1;
        }

        return null;
    }
}
