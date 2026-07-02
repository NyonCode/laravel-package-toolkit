<?php

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use RuntimeException;

trait HasNamespaceResolver
{
    /**
     * Get the base path of the package.
     */
    protected function getPackageBasePath(): string
    {
        if (class_exists(InstalledVersions::class)) {
            $packageName = InstalledVersions::getRootPackage()['name'] ?? null;
            if ($packageName && InstalledVersions::isInstalled($packageName)) {
                return InstalledVersions::getInstallPath($packageName) ?: dirname(__DIR__, 3);
            }
        }

        return dirname(__DIR__, 3);
    }

    /**
     * Get the filesystem path from a namespace.
     *
     * @param  string  $componentNamespace  The namespace to resolve
     */
    protected function getPathFromNamespace(string $componentNamespace): ?string
    {
        /** @var ClassLoader|null $composerAutoload */
        $composerAutoload = require $this->getPackageBasePath().'/vendor/autoload.php';

        if (! $composerAutoload instanceof ClassLoader) {
            return null;
        }

        $psr4Mappings = $composerAutoload->getPrefixesPsr4();
        $matchingPrefix = null;
        $basePath = null;

        foreach ($psr4Mappings as $prefix => $paths) {
            if (str_starts_with($componentNamespace, $prefix)) {
                if ($matchingPrefix === null || strlen($prefix) > strlen($matchingPrefix)) {
                    $matchingPrefix = $prefix;
                    $basePath = reset($paths);
                }
            }
        }

        if (! $matchingPrefix || ! $basePath) {
            return null;
        }

        $relativeNamespace = substr($componentNamespace, strlen($matchingPrefix));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace);

        return realpath($basePath.DIRECTORY_SEPARATOR.$relativePath);
    }

    /**
     * Get the namespace from a filesystem path.
     *
     * @param  string  $filePath  The path to the file
     * @return string|null The namespace of the file
     *
     * @throws RuntimeException If the file does not exist
     */
    protected function getNamespaceFromPath(string $filePath): ?string
    {
        $normalizedFilePath = realpath($filePath);
        if (! $normalizedFilePath) {
            throw new RuntimeException("File not found: $filePath");
        }

        $composerAutoload = require $this->getPackageBasePath().'/vendor/autoload.php';
        if (! $composerAutoload instanceof ClassLoader) {
            throw new RuntimeException('Composer autoloader not found.');
        }

        $psr4Mappings = $composerAutoload->getPrefixesPsr4();
        foreach ($psr4Mappings as $namespacePrefix => $paths) {
            foreach ($paths as $basePath) {
                $normalizedBasePath = realpath($basePath);
                if (! $normalizedBasePath) {
                    continue;
                }

                if (str_starts_with($normalizedFilePath, $normalizedBasePath)) {
                    $relativePath = ltrim(substr($normalizedFilePath, strlen($normalizedBasePath)), DIRECTORY_SEPARATOR);
                    $relativeDir = dirname($relativePath);
                    $relativeDir = ($relativeDir === '.' ? '' : $relativeDir);

                    $namespaceSuffix = str_replace(DIRECTORY_SEPARATOR, '\\', $relativeDir);

                    return rtrim($namespacePrefix, '\\')
                        .($namespaceSuffix ? '\\'.$namespaceSuffix : '');
                }
            }
        }

        return null;
    }
}
