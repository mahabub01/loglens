<?php

namespace MehrabHossain\LogLens\Contracts;

interface SystemMetricsCollectorInterface
{
    /**
     * Collect the current system metrics snapshot.
     *
     * @return array<string, mixed>
     */
    public function collect(): array;

    /**
     * Get the detected platform and environment details.
     *
     * @return array<string, mixed>
     */
    public function getPlatform(): array;

    /**
     * Determine if system resource monitoring is enabled.
     */
    public function isEnabled(): bool;
}
