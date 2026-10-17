<?php

namespace MehrabHossain\LogLens\Contracts;

interface AlertManagerInterface
{
    /**
     * Evaluate metrics against configured alert thresholds.
     *
     * @param  array<string, mixed>  $metricsContext
     * @return array<int, array<string, mixed>> Active alerts
     */
    public function evaluate(array $metricsContext): array;

    /**
     * Get all active alerts currently triggered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveAlerts(): array;

    /**
     * Clear all active alerts.
     */
    public function clearAlerts(): void;

    /**
     * Determine if alert evaluation is enabled.
     */
    public function isEnabled(): bool;
}
