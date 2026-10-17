<?php

namespace MehrabHossain\LogLens\Services;

use MehrabHossain\LogLens\Contracts\LogAnalyzerInterface;
use MehrabHossain\LogLens\ValueObjects\ErrorGroup;
use MehrabHossain\LogLens\ValueObjects\LogEntry;

class LogAnalyzer implements LogAnalyzerInterface
{
    public function __construct(
        protected ErrorFingerprinter $fingerprinter
    ) {
    }

    /**
     * Analyze a collection of parsed log entries.
     */
    public function analyze(array $entries): array
    {
        $totalEntries = count($entries);
        $errorCount = 0;
        $warningCount = 0;
        $infoCount = 0;

        $severityBreakdown = [
            'EMERGENCY' => 0,
            'ALERT' => 0,
            'CRITICAL' => 0,
            'ERROR' => 0,
            'WARNING' => 0,
            'NOTICE' => 0,
            'INFO' => 0,
            'DEBUG' => 0,
        ];

        /** @var array<string, ErrorGroup> $groups */
        $groups = [];
        $timelineBuckets = [];

        foreach ($entries as $entry) {
            $sev = strtoupper($entry->severity);
            if (isset($severityBreakdown[$sev])) {
                $severityBreakdown[$sev]++;
            } else {
                $severityBreakdown[$sev] = 1;
            }

            if ($entry->isError()) {
                $errorCount++;

                // Fingerprint and group error
                $fp = $this->fingerprinter->fingerprint($entry);
                $entry->fingerprint = $fp;

                if (isset($groups[$fp])) {
                    $groups[$fp]->recordOccurrence($entry);
                } else {
                    $groups[$fp] = new ErrorGroup(
                        fingerprint: $fp,
                        exceptionClass: $entry->exceptionClass ?? 'StandardError',
                        normalizedMessage: $this->fingerprinter->normalizeMessage($entry->message),
                        severity: $entry->severity,
                        count: 1,
                        firstSeen: $entry->timestamp,
                        lastSeen: $entry->timestamp,
                        sampleEntry: $entry
                    );
                }
            } elseif ($sev === 'WARNING') {
                $warningCount++;
            } else {
                $infoCount++;
            }

            // Timeline bucket (by Hour: Y-m-d H:00)
            $bucketKey = $entry->timestamp->format('M d H:00');
            $timelineBuckets[$bucketKey] = ($timelineBuckets[$bucketKey] ?? 0) + 1;
        }

        // Sort error groups by occurrence count descending
        uasort($groups, function (ErrorGroup $a, ErrorGroup $b) {
            return $b->count <=> $a->count;
        });

        // Top 5 frequent errors formatted for charts
        $topErrors = [];
        $i = 0;
        foreach ($groups as $group) {
            if ($i++ >= 5) {
                break;
            }
            $shortLabel = mb_strimwidth($group->exceptionClass . ': ' . $group->normalizedMessage, 0, 45, '...');
            $topErrors[] = [
                'label' => $shortLabel,
                'count' => $group->count,
                'fingerprint' => $group->fingerprint,
            ];
        }

        // Prepare chronological timeline labels and counts
        ksort($timelineBuckets);
        $timeline = [
            'labels' => array_keys($timelineBuckets),
            'counts' => array_values($timelineBuckets),
        ];

        return [
            'total_entries' => $totalEntries,
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'info_count' => $infoCount,
            'unique_error_groups' => count($groups),
            'groups' => array_values($groups),
            'severity_breakdown' => $severityBreakdown,
            'timeline' => $timeline,
            'top_errors' => $topErrors,
        ];
    }
}
