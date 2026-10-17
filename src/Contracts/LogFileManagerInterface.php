<?php

namespace MehrabHossain\LogLens\Contracts;

use MehrabHossain\LogLens\ValueObjects\LogFile;

interface LogFileManagerInterface
{
    /**
     * Get all authorized log files across configured directories.
     *
     * @return array<string, LogFile>
     */
    public function getLogFiles(): array;

    /**
     * Find an authorized log file by its secure identifier.
     */
    public function findByIdentifier(string $identifier): ?LogFile;

    /**
     * Resolve an authorized log file or throw a security/not-found exception.
     *
     * @throws \MehrabHossain\LogLens\Exceptions\LogLensSecurityException
     * @throws \MehrabHossain\LogLens\Exceptions\LogFileNotFoundException
     */
    public function resolveFile(string $identifier): LogFile;

    /**
     * Validate whether a file path is strictly inside the configured directories
     * and does not violate symbolic link or path traversal boundaries.
     */
    public function isAuthorizedPath(string $filePath): bool;
}
