<?php

namespace MehrabHossain\LogLens\Contracts;

use MehrabHossain\LogLens\ValueObjects\LogEntry;

interface LogParserInterface
{
    /**
     * Parse a raw chunk or block of log text into structured LogEntry objects.
     *
     * @param  string  $rawContent
     * @return array<int, LogEntry>
     */
    public function parseContent(string $rawContent): array;

    /**
     * Parse a single multiline block into a LogEntry object.
     *
     * @param  string  $block
     * @param  int  $index
     * @return LogEntry|null
     */
    public function parseBlock(string $block, int $index = 0): ?LogEntry;
}
