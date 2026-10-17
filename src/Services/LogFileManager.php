<?php

namespace MehrabHossain\LogLens\Services;

use DateTimeImmutable;
use MehrabHossain\LogLens\Contracts\LogFileManagerInterface;
use MehrabHossain\LogLens\Exceptions\LogFileNotFoundException;
use MehrabHossain\LogLens\Exceptions\LogLensSecurityException;
use MehrabHossain\LogLens\ValueObjects\LogFile;

class LogFileManager implements LogFileManagerInterface
{
    /**
     * Get all authorized log files across configured directories.
     *
     * @return array<string, LogFile> Keyed by file identifier
     */
    public function getLogFiles(): array
    {
        $files = [];
        $allowedExtensions = config('loglens.logs.allowed_extensions', ['log']);

        // Primary writable log paths
        $primaryPaths = config('loglens.logs.paths', [storage_path('logs')]);
        foreach ($primaryPaths as $directory) {
            $this->scanDirectory($directory, $allowedExtensions, false, $files);
        }

        // Additional read-only log sources
        $additionalPaths = config('loglens.logs.additional_paths', []);
        foreach ($additionalPaths as $directory) {
            $this->scanDirectory($directory, $allowedExtensions, true, $files);
        }

        // Sort files by last modified descending (newest first)
        uasort($files, function (LogFile $a, LogFile $b) {
            return $b->modifiedAt <=> $a->modifiedAt;
        });

        return $files;
    }

    /**
     * Scan a specific directory for authorized log files.
     */
    protected function scanDirectory(
        string $directory,
        array $allowedExtensions,
        bool $isReadOnly,
        array &$results
    ): void {
        if (! is_dir($directory)) {
            return;
        }

        $canonicalBase = realpath($directory);
        if ($canonicalBase === false) {
            return;
        }

        $iterator = new \DirectoryIterator($canonicalBase);

        foreach ($iterator as $item) {
            if ($item->isDot() || $item->isDir()) {
                continue;
            }

            $extension = strtolower($item->getExtension());
            if (! in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            $realPath = $item->getRealPath();
            if ($realPath === false) {
                continue;
            }

            // Security: verify symlink target or file is strictly within the allowed directory
            if (! $this->isInsideAllowedDirectory($realPath, $canonicalBase)) {
                continue;
            }

            $identifier = $this->generateIdentifier($realPath);
            $modifiedTimestamp = $item->getMTime();
            $modifiedAt = (new DateTimeImmutable())->setTimestamp($modifiedTimestamp ?: time());

            $results[$identifier] = new LogFile(
                identifier: $identifier,
                filename: $item->getFilename(),
                relativePath: $this->getRelativePath($canonicalBase, $realPath),
                realPath: $realPath,
                sizeInBytes: (int) $item->getSize(),
                modifiedAt: $modifiedAt,
                isWritable: ! $isReadOnly && $item->isWritable(),
                isReadOnly: $isReadOnly
            );
        }
    }

    /**
     * Find an authorized log file by its secure identifier.
     */
    public function findByIdentifier(string $identifier): ?LogFile
    {
        $all = $this->getLogFiles();

        return $all[$identifier] ?? null;
    }

    /**
     * Resolve an authorized log file or throw an appropriate exception.
     */
    public function resolveFile(string $identifier): LogFile
    {
        // Reject empty or obviously malformed identifiers
        if (empty(trim($identifier)) || preg_match('/[^a-zA-Z0-9_\-]/', $identifier)) {
            throw new LogLensSecurityException("Invalid file identifier provided: {$identifier}");
        }

        $file = $this->findByIdentifier($identifier);

        if (! $file) {
            throw new LogFileNotFoundException("Requested log file not found or unauthorized.");
        }

        // Final sanity check on canonical realpath
        if (! $this->isAuthorizedPath($file->realPath)) {
            throw new LogLensSecurityException("Log file path traversal or unauthorized access detected.");
        }

        return $file;
    }

    /**
     * Validate whether a file path is strictly inside the configured directories.
     */
    public function isAuthorizedPath(string $filePath): bool
    {
        $realPath = realpath($filePath);
        if ($realPath === false) {
            return false;
        }

        $allConfiguredDirs = array_merge(
            config('loglens.logs.paths', [storage_path('logs')]),
            config('loglens.logs.additional_paths', [])
        );

        foreach ($allConfiguredDirs as $dir) {
            $canonicalDir = realpath($dir);
            if ($canonicalDir && $this->isInsideAllowedDirectory($realPath, $canonicalDir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a canonical path resides strictly inside a canonical directory.
     */
    protected function isInsideAllowedDirectory(string $targetPath, string $baseDirectory): bool
    {
        $base = rtrim($baseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($targetPath, $base);
    }

    /**
     * Compute a deterministic URL-safe identifier for a real file path.
     */
    protected function generateIdentifier(string $realPath): string
    {
        return substr(hash('sha256', $realPath), 0, 16);
    }

    /**
     * Get path relative to base directory for clean display.
     */
    protected function getRelativePath(string $baseDir, string $realPath): string
    {
        $prefix = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($realPath, $prefix)) {
            return substr($realPath, strlen($prefix));
        }

        return basename($realPath);
    }
}
