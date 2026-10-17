<?php

namespace MehrabHossain\LogLens\Contracts;

interface LogAnalyzerInterface
{
    /**
     * Analyze a collection of parsed log entries to extract error groups,
     * frequency metrics, and timeline statistics for Chart.js visualization.
     *
     * @param  array<\MehrabHossain\LogLens\ValueObjects\LogEntry>  $entries
     * @return array{
     *     total_entries: int,
     *     error_count: int,
     *     warning_count: int,
     *     info_count: int,
     *     unique_error_groups: int,
     *     groups: array<\MehrabHossain\LogLens\ValueObjects\ErrorGroup>,
     *     severity_breakdown: array<string, int>,
     *     timeline: array{labels: array<int, string>, counts: array<int, int>},
     *     top_errors: array<int, array{label: string, count: int, fingerprint: string}>
     * }
     */
    public function analyze(array $entries): array;
}
