<?php

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use RuntimeException;

trait HasNamespaceResolver
{
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
     * @param string $componentNamespace
     * @return string|null
     */
    protected function getPathFromNamespace(string $componentNamespace): ?string
    {
        /** @var ClassLoader|null $composerAutoload */
        $composerAutoload = require $this->getPackageBasePath() . '/vendor/autoload.php';

        if (!$composerAutoload instanceof ClassLoader) {
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

        if (!$matchingPrefix || !$basePath) {
            return null;
        }

        $relativeNamespace = substr($componentNamespace, strlen($matchingPrefix));
        $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace);

        return realpath($basePath . DIRECTORY_SEPARATOR . $relativePath);
    }
}