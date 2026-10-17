<?php

namespace MehrabHossain\LogLens\Tests\Performance;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use MehrabHossain\LogLens\Contracts\DatabaseMetricsCollectorInterface;
use MehrabHossain\LogLens\Contracts\HttpMetricsCollectorInterface;
use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Contracts\LogParserInterface;
use MehrabHossain\LogLens\Contracts\LogReaderInterface;
use MehrabHossain\LogLens\Contracts\MetricsStoreInterface;
use MehrabHossain\LogLens\Contracts\QueueMetricsCollectorInterface;
use MehrabHossain\LogLens\Services\Storage\RedisMetricsStore;
use MehrabHossain\LogLens\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

class PerformanceBenchmarkTest extends TestCase
{
    protected static array $benchmarkResults = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePackage();
        $this->authorizeAccess();
        config([
            'loglens.database.enabled' => true,
            'loglens.http.enabled' => true,
            'loglens.queue.enabled' => true,
            'loglens.scheduler.enabled' => true,
            'loglens.system.enabled' => true,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        // Dump benchmark summary to console for documentation verification
        if (! empty(self::$benchmarkResults)) {
            echo "\n\n==================== LOGLENS PERFORMANCE BENCHMARKS ====================\n";
            foreach (self::$benchmarkResults as $test => $metrics) {
                echo sprintf("--- %s ---\n", $test);
                foreach ($metrics as $key => $val) {
                    echo sprintf("  %-28s: %s\n", $key, is_numeric($val) ? number_format($val, 2) : $val);
                }
            }
            echo "========================================================================\n\n";
        }
        parent::tearDownAfterClass();
    }

    public function test_large_log_file_parsing_benchmark(): void
    {
        $lineCount = 10000;
        $content = '';
        $levels = ['INFO', 'WARNING', 'ERROR', 'CRITICAL', 'DEBUG'];

        for ($i = 1; $i <= $lineCount; $i++) {
            $lvl = $levels[$i % 5];
            $content .= "[2026-10-17 12:00:00] local.{$lvl}: Simulated application event #{$i} with user_id=123 context data {\"req_id\":{$i}}\n";
        }

        $filePath = $this->createLogFile('large_bench.log', $content);
        $fileSizeMb = round(filesize($filePath) / 1048576, 2);

        /** @var LogFileManagerInterface $manager */
        $manager = app(LogFileManagerInterface::class);
        $logFile = collect($manager->getLogFiles())->firstWhere('filename', 'large_bench.log');

        /** @var LogReaderInterface $reader */
        $reader = app(LogReaderInterface::class);

        $startMem = memory_get_usage(true);
        $startTime = microtime(true);

        // Read bounded paginated entries
        $result = $reader->readPaginated($logFile, page: 1, perPage: 100);

        $parseTime = microtime(true);
        $endMem = memory_get_usage(true);
        $peakMem = memory_get_peak_usage(true);

        $totalDurationMs = ($parseTime - $startTime) * 1000;
        $memGrowthMb = ($endMem - $startMem) / 1048576;

        self::$benchmarkResults['Large Log Parsing (10k lines / ' . $fileSizeMb . ' MB)'] = [
            'Total Lines in File' => $lineCount,
            'File Size (MB)' => $fileSizeMb,
            'Entries Parsed (Page 1)' => count($result['entries']),
            'Total Scanned' => $result['total_scanned'],
            'Duration (ms)' => $totalDurationMs,
            'Memory Growth (MB)' => $memGrowthMb,
            'Peak Process RAM (MB)' => $peakMem / 1048576,
        ];

        $this->assertCount(100, $result['entries']);
        $this->assertLessThan(2000, $totalDurationMs, 'Streaming & parsing 100 entries from 10k log file must complete within 2s.');
        $this->assertLessThan(15, $memGrowthMb, 'Memory growth during bounded log reading must be < 15MB.');
    }

    public function test_rapidly_growing_log_file_tail_benchmark(): void
    {
        $initialContent = "[2026-10-17 12:00:00] local.INFO: Baseline log line\n";
        $filePath = $this->createLogFile('tail_bench.log', $initialContent);

        /** @var LogFileManagerInterface $manager */
        $manager = app(LogFileManagerInterface::class);
        $logFile = collect($manager->getLogFiles())->firstWhere('filename', 'tail_bench.log');

        /** @var LogReaderInterface $reader */
        $reader = app(LogReaderInterface::class);
        $initialRead = $reader->readFromOffset($logFile, offset: 0);
        $offset = $initialRead['new_offset'];

        // Append 1,000 new lines rapidly
        $appendContent = '';
        for ($i = 1; $i <= 1000; $i++) {
            $appendContent .= "[2026-10-17 12:00:01] local.ERROR: Rapid error stream #{$i}\n";
        }
        file_put_contents($filePath, $appendContent, FILE_APPEND);

        // Re-resolve to update sizeInBytes in LogFile
        $logFile = collect($manager->getLogFiles())->firstWhere('filename', 'tail_bench.log');

        $startTime = microtime(true);
        $incremental = $reader->readFromOffset($logFile, offset: $offset);
        $tailDurationMs = (microtime(true) - $startTime) * 1000;

        self::$benchmarkResults['Rapidly Growing Log Stream (1k appended lines)'] = [
            'Appended Lines' => 1000,
            'Stream Read Time (ms)' => $tailDurationMs,
            'New Entries Streamed' => count($incremental['entries']),
            'Throughput (lines/sec)' => round(1000 / (max($tailDurationMs, 0.001) / 1000), 0),
        ];

        $this->assertCount(1000, $incremental['entries']);
        $this->assertLessThan(300, $tailDurationMs, 'Streaming 1,000 appended lines from offset must execute in < 300ms.');
    }

    public function test_high_query_event_volume_benchmark(): void
    {
        /** @var DatabaseMetricsCollectorInterface $collector */
        $collector = app(DatabaseMetricsCollectorInterface::class);
        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        $queryCount = 2000;
        $connection = DB::connection();

        $startMem = memory_get_usage(true);
        $startTime = microtime(true);

        for ($i = 1; $i <= $queryCount; $i++) {
            $event = new QueryExecuted(
                sql: "SELECT * FROM orders WHERE user_id = {$i} AND status = 'active'",
                bindings: [],
                time: ($i % 20 === 0) ? 650.0 : 12.5, // Occasional slow query
                connection: $connection
            );
            $collector->handle($event);
        }

        $durationMs = (microtime(true) - $startTime) * 1000;
        $endMem = memory_get_usage(true);
        $summary = $store->getDatabaseSummary();

        self::$benchmarkResults['High Query Event Volume (2,000 DB events)'] = [
            'Query Events Handled' => $queryCount,
            'Total Execution Time (ms)' => $durationMs,
            'Avg Time per Query (µs)' => round(($durationMs / $queryCount) * 1000, 2),
            'Throughput (queries/sec)' => round($queryCount / ($durationMs / 1000), 0),
            'Total Recorded in Summary' => $summary['total_queries'],
            'Slow Queries Detected' => $summary['slow_query_count'],
            'Memory Growth (MB)' => ($endMem - $startMem) / 1048576,
        ];

        $this->assertSame($queryCount, $summary['total_queries']);
        $this->assertSame(100, $summary['slow_query_count']);
        $this->assertLessThan(2500, $durationMs, 'Handling 2,000 query events must complete in < 2.5s.');
    }

    public function test_high_http_request_volume_benchmark(): void
    {
        /** @var HttpMetricsCollectorInterface $collector */
        $collector = app(HttpMetricsCollectorInterface::class);
        /** @var MetricsStoreInterface $store */
        $store = app(MetricsStoreInterface::class);
        $store->reset();

        $requestCount = 2000;
        $statuses = [200, 200, 200, 201, 204, 404, 500];

        $startMem = memory_get_usage(true);
        $startTime = microtime(true);

        for ($i = 1; $i <= $requestCount; $i++) {
            $req = Request::create("/api/v1/resource/{$i}", 'GET');
            $status = $statuses[$i % count($statuses)];
            $res = new Response('content', $status);

            $collector->start($req);
            $collector->terminate($req, $res);
        }

        $durationMs = (microtime(true) - $startTime) * 1000;
        $endMem = memory_get_usage(true);

        // Benchmark percentiles calculation latency
        $calcStart = microtime(true);
        $summary = $store->getHttpSummary();
        $calcDurationMs = (microtime(true) - $calcStart) * 1000;

        self::$benchmarkResults['High HTTP Request Volume (2,000 requests)'] = [
            'Requests Tracked' => $requestCount,
            'Total Collection Time (ms)' => $durationMs,
            'Avg Time per Request (µs)' => round(($durationMs / $requestCount) * 1000, 2),
            'Percentile Calc Time (ms)' => $calcDurationMs,
            'P50 Median Latency (ms)' => $summary['median_response_time_ms'],
            'P95 Latency (ms)' => $summary['p95_response_time_ms'],
            'P99 Latency (ms)' => $summary['p99_response_time_ms'],
            'Memory Growth (MB)' => ($endMem - $startMem) / 1048576,
        ];

        $this->assertSame($requestCount, $summary['total_requests']);
        $this->assertLessThan(50, $calcDurationMs, 'Percentiles statistical calculation must take < 50ms.');
        $this->assertLessThan(2000, $durationMs, 'Instrumenting 2,000 HTTP requests must take < 2.0s.');
    }

    public function test_dashboard_response_times_benchmark(): void
    {
        // Populate sample data for all dashboards
        $this->createLogFile('bench_sample.log', "[2026-10-17 12:00:00] local.ERROR: Sample error\n");

        $routes = [
            'Overview Dashboard' => '/loglens',
            'Log Viewer' => '/loglens/logs',
            'Error Analysis' => '/loglens/errors',
            'HTTP Performance' => '/loglens/http',
            'Database Monitoring' => '/loglens/database',
            'Apache Status' => '/loglens/apache',
            'Server Health' => '/loglens/server-health',
            'Queue Monitoring' => '/loglens/queue',
            'Scheduler Monitoring' => '/loglens/scheduler',
        ];

        $timings = [];
        foreach ($routes as $label => $uri) {
            $start = microtime(true);
            $res = $this->get($uri);
            $durationMs = round((microtime(true) - $start) * 1000, 2);

            $this->assertSame(200, $res->getStatusCode(), "Endpoint {$uri} must return 200 OK.");
            $timings[$label] = $durationMs . ' ms';
        }

        self::$benchmarkResults['Dashboard Response Latencies (Warm Cache)'] = $timings;
    }

    public function test_long_running_worker_memory_stability_benchmark(): void
    {
        /** @var QueueMetricsCollectorInterface $queue */
        $queue = app(QueueMetricsCollectorInterface::class);
        $queue->reset();

        $iterations = 1000;
        $checkpoints = [];

        $mockJob = new class {
            public function getJobId(): string { return 'bench-job'; }
            public function resolveName(): string { return 'App\\Jobs\\BenchJob'; }
            public function getQueue(): string { return 'default'; }
        };

        for ($i = 1; $i <= $iterations; $i++) {
            $queue->handleJobProcessing(new JobProcessing('sync', $mockJob));
            $queue->handleJobProcessed(new JobProcessed('sync', $mockJob));

            if ($i === 1 || $i === 250 || $i === 500 || $i === 1000) {
                $checkpoints["Cycle {$i} RAM (MB)"] = round(memory_get_usage(true) / 1048576, 2);
            }
        }

        $initialRam = $checkpoints['Cycle 1 RAM (MB)'];
        $finalRam = $checkpoints['Cycle 1000 RAM (MB)'];
        $ramGrowth = $finalRam - $initialRam;

        $checkpoints['Total RAM Growth (MB)'] = round($ramGrowth, 2);
        self::$benchmarkResults['Long-Running Worker Stability (1,000 Job Cycles)'] = $checkpoints;

        $this->assertLessThan(5.0, $ramGrowth, 'Memory leak in long-running worker queue observer must be < 5.0 MB across 1,000 cycles.');
    }
}
