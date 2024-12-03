<?php declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Exceptions;

use Exception;
use Illuminate\Support\Str;

class PackagerException extends Exception
{
    /**
     * Create a new exception for an invalid package name.
     *
     * @return static
     */
    public static function missingName(): static
    {
        return new static('This package does not have a name. You can set one with `$package->name("")');
    }

    /**
     * Create a new exception when a specified folder does not exist.
     *
     * @param string $directory The path of the folder that does not exist.
     * @return static
     */
    public static function directoryNotFound(string $directory): static
    {
        return new static("Folder does not exist: [$directory]");
    }

    /**
     * Create a new exception when a specified folder is empty.
     *
     * @param string $directory The path of the folder that is empty.
     * @return static
     */
    public static function directoryIsEmpty(string $directory): static
    {
        return new static("Folder is empty: [$directory]");
    }

    /**
     * Create a new exception when a configuration file does not return an array.
     *
     * @param string $fileName The name of the configuration file.
     * @return static
     */
    public static function configMustReturnArray(string $fileName): static
    {
        return new static("Configuration file [$fileName] must return an array.");
    }

    /**
     * Create a new exception when a specified file does not exist.
     *
     * @param string $file The name of the file that does not exist.
     * @param string|null $type The type of the file (e.g., route, config, etc.) or null if not specified.
     * @return static
     */
    public static function fileNotExist(string $file, string|null $type = null): static
    {
        return new static(
            $type ? Str::title($type) . " file [$file] does not exist." : "File [$file] does not exist."
        );
    }

    /**
     * Create a new exception for an invalid language directory name.
     *
     * @param string $langDirectory The name of the invalid language directory.
     * @return static
     */
    public static function invalidNameLanguageDirectory(string $langDirectory): static
    {
        return new static(
            "Invalid language directory [$langDirectory]. Directory name must be one of the supported languages."
        );
    }

    public static function invalidComponentName(mixed $name): static
    {
        return new static("Invalid name [$name]. The name must be a string.");
    }

    public static function invalidComponentClass(mixed $name, mixed $component): static
    {
        return new static("Invalid component class [$component] for [$name]. The value must be a valid class name.");
    }
}
