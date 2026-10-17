<?php

namespace MehrabHossain\LogLens\Contracts;

use Illuminate\Database\Events\QueryExecuted;

interface DatabaseMetricsCollectorInterface
{
    /**
     * Register passive event listener on DB::listen without executing any additional queries.
     */
    public function register(): void;

    /**
     * Handle an observed QueryExecuted event passively.
     *
     * @param  QueryExecuted  $event
     */
    public function handle(QueryExecuted $event): void;

    /**
     * Determine if database monitoring is currently enabled.
     */
    public function isEnabled(): bool;

    /**
     * Determine if the listener has been registered.
     */
    public function isRegistered(): bool;
}
