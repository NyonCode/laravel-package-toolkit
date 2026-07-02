<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

trait FilesResolver
{
    /**
     * @var string The base path of the package
     */
    private string $basePath = '';

    /**
     * Get the base path of the package.
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Set the base path of the package.
     *
     * @param  string  $basePath  The base path to set
     */
    public function hasBasePath(string $basePath): string
    {
        // Normalize path separators for cross-platform compatibility
        $basePath = $this->normalizePath($basePath);

        if (Str::contains($basePath, 'src'.DIRECTORY_SEPARATOR.'Providers')) {
            return $this->basePath = Str::before($basePath, DIRECTORY_SEPARATOR.'Providers');
        }

        return $this->basePath = $basePath;
    }

    /**
     * Normalize path separators for cross-platform compatibility.
     */
    private function normalizePath(string $path): string
    {
        // Convert all separators to the current OS separator
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Remove duplicate separators
        $path = preg_replace('#'.preg_quote(DIRECTORY_SEPARATOR).'+#', DIRECTORY_SEPARATOR, $path);

        // Remove trailing separator unless it's root
        if (strlen($path) > 1 && str_ends_with($path, DIRECTORY_SEPARATOR)) {
            $path = rtrim($path, DIRECTORY_SEPARATOR);
        }

        return $path;
    }

    /**
     * Determine if the given path is absolute.
     */
    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:/', $path) === 1;
    }

    /**
     * Join path components with proper separators.
     */
    private function joinPaths(string ...$parts): string
    {
        $parts = array_filter($parts, fn ($part) => $part !== '');
        $path = implode(DIRECTORY_SEPARATOR, $parts);

        return $this->normalizePath($path);
    }

    /**
     * Get the full path of a given directory or file.
     *
     * @param  string  $path  The relative path
     */
    public function path(string $path): string
    {
        $path = $this->normalizePath($path);

        return $this->joinPaths($this->basePath, ltrim($path, DIRECTORY_SEPARATOR));
    }

    /**
     * Get real path with better cross-platform handling.
     */
    private function getRealPath(string $path): string|false
    {
        $realPath = realpath($path);

        // On Windows, realpath might fail for non-existent paths
        // Try to resolve manually if realpath fails
        if ($realPath === false && $this->isWindows()) {
            $realPath = $this->resolveWindowsPath($path);
        }

        return $realPath;
    }

    /**
     * Check if running on Windows.
     */
    private function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * Resolve Windows paths manually when realpath fails.
     */
    private function resolveWindowsPath(string $path): string|false
    {
        $path = $this->normalizePath($path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $resolved = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($resolved);
            } else {
                $resolved[] = $part;
            }
        }

        $resolvedPath = implode(DIRECTORY_SEPARATOR, $resolved);

        // Handle drive letters on Windows
        if ($this->isWindows() && preg_match('/^[A-Za-z]:/', $path)) {
            $resolvedPath = substr($path, 0, 2).DIRECTORY_SEPARATOR.ltrim($resolvedPath, DIRECTORY_SEPARATOR);
        }

        return $resolvedPath;
    }

    /**
     * Get files from a given path.
     *
     * @param  string  $path  The path to search for files
     * @return SplFileInfo[]
     */
    private function getFiles(string $path): array
    {
        $fullPath = $this->path($path);

        if (! is_dir($fullPath) || ! is_readable($fullPath)) {
            throw new DirectoryNotFoundException("Directory [$fullPath] does not exist or is not readable");
        }

        $loadedFiles = File::files($fullPath);
        $files = [];

        foreach ($loadedFiles as $loadedFile) {
            try {
                $fileInfo = $this->getFileInfo($loadedFile->getPathname());
                if ($fileInfo->isReadable()) {
                    $files[] = $fileInfo;
                }
            } catch (Exception) {
                // Skip unreadable files rather than failing completely
                continue;
            }
        }

        return $files;
    }

    /**
     * Get file information as SplFileInfo object.
     *
     * @param  string  $filePath  The path to the file
     *
     * @throws FileNotFoundException
     */
    private function getFileInfo(string $filePath): SplFileInfo
    {
        $normalizedPath = $this->normalizePath($filePath);

        if (! is_file($normalizedPath)) {
            throw new FileNotFoundException("File [$normalizedPath] does not exist");
        }

        if (! is_readable($normalizedPath)) {
            throw new FileNotFoundException("File [$normalizedPath] is not readable");
        }

        return new SplFileInfo($normalizedPath);
    }

    /**
     * Validate if the given directory exists.
     *
     * @param  string  $path  The path of the directory to validate
     *
     * @throws Exception If the directory does not exist
     */
    private function validDirectory(string $path): void
    {
        $normalizedPath = $this->normalizePath($path);
        $realPath = $this->getRealPath($normalizedPath);

        if ($realPath === false || ! is_dir($realPath)) {
            throw new DirectoryNotFoundException(
                "Directory [$path] does not exist"
            );
        }
    }

    /**
     * Discover files from the specified path.
     *
     * @param  string  $path  The path to discover files from
     * @return SplFileInfo[]
     *
     * @throws Exception If the directory does not exist
     */
    private function discoverFiles(string $path): array
    {
        $fullPath = $this->path($path);
        $this->validDirectory($fullPath);

        return $this->getFiles($path);
    }

    /**
     * Resolves the path to a file in the specified directory.
     *
     * @param  string  $file  The name of the file to resolve
     * @param  string  $directory  Directory name where files are located
     * @return string Full path if the file exists, or empty string if not found
     */
    private function resolveFilePath(string $file, string $directory): string
    {
        $file = $this->normalizePath($file);

        // If file path starts with .. or / it's already a relative/absolute path
        if (Str::startsWith($file, ['..', '/', DIRECTORY_SEPARATOR]) || preg_match('/^[A-Za-z]:/', $file)) {
            $relativePath = $this->path($file);
            if (is_file($relativePath)) {
                return $relativePath;
            }
        }

        // Try direct path construction
        $directPath = $this->path($this->joinPaths($directory, $file));
        if (is_file($directPath)) {
            return $directPath;
        }

        return '';
    }

    /**
     * Resolve files from the specified directory.
     *
     * @param  string|string[]|null  $files  The files to resolve. If null, discover all files from the specified directory.
     * @param  string  $directory  The directory where the files are located
     * @param  string  $type  The type of files to resolve
     * @return SplFileInfo[] The resolved files
     *
     * @throws FileNotFoundException If any file does not exist
     * @throws Exception
     */
    public function resolveFiles(string|array|null $files, string $directory = '', string $type = ''): array
    {
        /** @var SplFileInfo[] $filesInfo */
        $filesInfo = [];

        if (! empty($files)) {
            if (! is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                $filePath = $this->resolveFilePath($file, $directory);

                if (empty($filePath) || ! is_file($filePath)) {
                    $errorMessage = $type
                        ? (Str::title($type)." file [$file] does not exist in directory [$directory].")
                        : "File [$file] does not exist in directory [$directory].";

                    throw new FileNotFoundException($errorMessage);
                }

                $filesInfo[] = $this->getFileInfo($filePath);
            }

            return $filesInfo;
        }

        return $this->discoverFiles($directory);
    }
}
