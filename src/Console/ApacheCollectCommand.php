<?php

namespace MehrabHossain\LogLens\Console;

use Illuminate\Console\Command;
use MehrabHossain\LogLens\Contracts\ApacheCollectorInterface;

class ApacheCollectCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'loglens:apache-collect
                            {--interval=1 : Target collection interval in seconds}
                            {--iterations=0 : Number of iterations to execute (0 for infinite daemon)}
                            {--once : Run a single collection cycle and terminate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the shared Apache mod_status metrics collector daemon';

    protected bool $running = true;

    /**
     * Execute the console command.
     */
    public function handle(ApacheCollectorInterface $collector): int
    {
        $interval = max(0.2, (float) $this->option('interval'));
        $maxIterations = $this->option('once') ? 1 : (int) $this->option('iterations');

        $this->info('Starting LogLens Apache mod_status collector...');
        $this->line("Target collection interval: <info>{$interval}s</info>");
        if ($maxIterations > 0) {
            $this->line("Execution limit: <info>{$maxIterations} iteration(s)</info>");
        } else {
            $this->line('Running in <info>daemon mode</info>. Press Ctrl+C to terminate.');
        }

        // Register signal handlers for graceful shutdown if pcntl is available
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function () {
                $this->running = false;
                $this->warn("\nShutdown signal received. Exiting gracefully...");
            });
            pcntl_signal(SIGTERM, function () {
                $this->running = false;
                $this->warn("\nTermination signal received. Exiting gracefully...");
            });
        }

        $iteration = 0;
        $hasErrors = false;

        while ($this->running) {
            $cycleStart = microtime(true);
            $iteration++;

            $result = $collector->collect();

            if ($result['success'] ?? false) {
                $m = $result['metrics'];
                $rps = $m['rps'] !== null ? number_format($m['rps'], 1) : 'N/A';
                $bps = $m['bps'] !== null ? $this->formatBytes($m['bps']) . '/s' : 'N/A';
                $busy = $m['busy_workers'] ?? 'N/A';
                $idle = $m['idle_workers'] ?? 'N/A';

                $this->line(sprintf(
                    '[%s] <info>OK</info> - Busy: %s, Idle: %s | RPS: %s | Throughput: %s',
                    date('H:i:s'),
                    $busy,
                    $idle,
                    $rps,
                    $bps
                ));
            } else {
                $hasErrors = true;
                $this->error(sprintf(
                    '[%s] ERROR: %s',
                    date('H:i:s'),
                    $result['error'] ?? 'Unknown error'
                ));
            }

            if ($maxIterations > 0 && $iteration >= $maxIterations) {
                break;
            }

            // Calculate precise sleep duration to maintain 1-second cadence
            $elapsed = microtime(true) - $cycleStart;
            $sleepSeconds = max(0.0, $interval - $elapsed);
            if ($sleepSeconds > 0) {
                usleep((int) ($sleepSeconds * 1_000_000));
            }
        }

        $this->info("Apache collector stopped after {$iteration} iteration(s).");

        return ($this->option('once') && $hasErrors) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Format bytes into a human-readable string.
     */
    protected function formatBytes(float $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return round($bytes, 0) . ' B';
    }
}
