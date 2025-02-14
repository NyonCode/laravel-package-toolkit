<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;
use Exception;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

trait FilesResolver
{
    public string $basePath = '';

    /**
     * Get the base path of the package.
     *
     * @return string
     */
    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * Set the base path of the package.
     *
     * @param string $basePath The base path to set
     *
     * @return string
     */
    public function hasBasePath(string $basePath): string
    {
        if (Str::contains($basePath, 'src/Providers')) {
            return $this->basePath = Str::before($basePath, '/Providers');
        }

        return $this->basePath = $basePath;
    }

    /**
     * Get the full path of a given directory or file.
     *
     * @param string $path The relative path
     *
     * @return string
     */
    public function path(string $path): string
    {
        return $this->basePath .
            DIRECTORY_SEPARATOR .
            ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Get files from a given path.
     *
     * @param string $path The path to search for files
     *
     * @return SplFileInfo[]
     */
    private function getFiles(string $path): array
    {
        $loadedFiles = File::files($this->path($path));

        foreach ($loadedFiles as $loadedFile) {
            $files[] = $this->getFileInfo($loadedFile->getPathname());
        }

        if (!empty($files)) {
            return $files;
        }

        return [];
    }

    /**
     * Get file information as SplFileInfo object.
     *
     * @param string $filePath The path to the file
     *
     * @return SplFileInfo
     */
    private function getFileInfo(string $filePath): SplFileInfo
    {
        return new SplFileInfo($filePath);
    }

    /**
     * Validate if the given folder exists.
     *
     * @param string $path The path of the folder to validate
     *
     * @throws Exception If the folder does not exist
     *
     * @return void
     */
    private function validFolder(string $path): void
    {
        $realPath = realpath($path);

        if ($realPath === false or !is_dir($realPath)) {
            throw new DirectoryNotFoundException(
                "Directory [$path] does not exist"
            );
        }
    }

    /**
     * Autoload files from the specified path.
     *
     * @param string $path The path to autoload files from
     *
     * @throws Exception If the folder does not exist
     *
     * @return SplFileInfo[]
     */
    private function autoloadFiles(string $path): array
    {
        $this->validFolder($this->path("../$path"));
        return $this->getFiles("../$path");
    }

    /**
     * Resolves the path to a file in the specified directory.
     *
     * @param string $file The name of the file to resolve
     * @param string $directory Directory name where files are located
     * @return string Full path if the file exists, or null if not found
     */
    private function resolveFilePath(string $file, string $directory): string
    {
        if (Str::startsWith($file, '..')) {
            $relativePath = $this->path($file);

            if (is_file($relativePath)) {
                return $relativePath;
            }
        }

        $directPath = $this->path(
            '..' .
                DIRECTORY_SEPARATOR .
                $directory .
                DIRECTORY_SEPARATOR .
                $file
        );

        if (is_file($directPath)) {
            return $directPath;
        }

        return '';
    }

    /**
     * Resolve files from the specified directory.
     *
     * @param string|array<string>|null $files     The files to resolve. If null, autoloads all files from the specified directory.
     * @param string            $directory The directory where the files are located
     * @param string            $type      The type of files to resolve (e.g. "route", "config", etc.)
     *
     * @throws FileNotFoundException If any file does not exist*@throws Exception
     * @throws Exception
     *
     * @return SplFileInfo[] The resolved files
     */
    public function resolveFiles(
        string|array|null $files, string $directory = '', string $type = ''
    ): array {
        /** @var SplFileInfo[] $filesInfo */
        $filesInfo = [];

        if (!empty($files)) {
            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $file) {
                $filePath = $this->resolveFilePath($file, $directory);

                if ( empty($filePath) && !is_file($filePath) ) {
                    throw new FileNotFoundException(
                        $type
                            ? Str::title($type) .
                                " file [$file] does not exist."
                            : "File [$file] does not exist."
                    );
                }

                $filesInfo[] = $this->getFileInfo($filePath);
            }

            return $filesInfo;
        }

        return $this->autoloadFiles($directory);
    }
}
