<?php declare(strict_types=1);

namespace NyonCode\LaravelPackageBuilder\Exceptions;

use Exception;
use Illuminate\Support\Str;

class PackagerException extends Exception
{
    /**
     * Create a new exception for an invalid package name.
     *
     * @return static
     */
    public static function invalidName(): self
    {
        return new static('This package does not have a name. You can set one with `$package->name("")');
    }

    /**
     * Create a new exception when a specified folder does not exist.
     *
     * @param string $folder The path of the folder that does not exist.
     * @return static
     */
    public static function folderNotExist(string $folder): self
    {
        return new static("Folder does not exist: [$folder]");
    }

    /**
     * Create a new exception when a specified folder is empty.
     *
     * @param string $folder The path of the folder that is empty.
     * @return static
     */
    public static function folderIsEmpty(string $folder): self
    {
        return new static("Folder is empty: [$folder]");
    }

    /**
     * Create a new exception when a configuration file does not return an array.
     *
     * @param string $fileName The name of the configuration file.
     * @return static
     */
    public static function configMustReturnArray(string $fileName): self
    {
        return new static("Configuration file [$fileName] must return an array.");
    }

    /**
     * Create a new exception when a specified file does not exist.
     *
     * @param string $routeFile The name of the file that does not exist.
     * @param string|null $type The type of the file (e.g., route, config, etc.) or null if not specified.
     * @return static
     */
    public static function fileNotExist(string $routeFile, string|null $type = null): self
    {
        return new static($type ? Str::title($type) . " file [$routeFile] does not exist." : "File [$routeFile] does not exist.");
    }

    /**
     * Create a new exception for an invalid language directory name.
     *
     * @param string $langDirectory The name of the invalid language directory.
     * @return static
     */
    public static function invalidNameLanguageDirectory(string $langDirectory): self
    {
        return new static("Invalid language directory [$langDirectory]. Directory name must be one of the supported languages.");
    }
}
