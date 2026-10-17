<?php

namespace MehrabHossain\LogLens\Contracts;

interface ApacheCollectorInterface
{
    /**
     * Perform a single collection cycle from the configured Apache mod_status endpoint.
     *
     * @return array<string, mixed> The collected metrics or error payload.
     */
    public function collect(): array;

    /**
     * Get the current status and health of the collector.
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array;

    /**
     * Determine if Apache monitoring is enabled.
     */
    public function isEnabled(): bool;
}
