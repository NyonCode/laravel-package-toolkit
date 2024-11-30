<?php declare(strict_types=1);

namespace NyonCode\LaravelPackageBuilder;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use NyonCode\LaravelPackageBuilder\Concerns\HasAboutCommand;
use NyonCode\LaravelPackageBuilder\Exceptions\PackagerException;
use NyonCode\LaravelPackageBuilder\Support\Enums\Language;
use NyonCode\LaravelPackageBuilder\Support\SplFileInfo;

class Packager
{

    use HasAboutCommand;

    public string $basePath;
    public string $name;

    /**
     * The short name of the package, or null if not set.
     *
     * @var string|null
     */
    private string|null $shortName = null;

    /**
     * Indicates whether the package is configurable.
     *
     * @var bool
     */
    public bool $isConfigurable = false;

    /**
     * The configuration files for the package.
     *
     * @var string[]|SplFileInfo[]|null
     */
    protected array|null $configFiles = null;

    /**
     * Indicates whether the package has route files.
     *
     * @var bool
     */
    public bool $isRoutable = false;

    /**
     * The route files for the package.
     *
     * @var string[]|SplFileInfo[]|null
     */
    protected array|null $routeFiles = null;

    /**
     * Indicates whether the package has migration files.
     *
     * @var bool
     */
    public bool $isMigratable = false;

    /**
     * The migration files for the package.
     *
     * @var string[]|SplFileInfo[]|null
     */
    protected array|null $migrationFiles = null;

    /**
     * Indicates whether the package is translatable.
     *
     * @var bool
     */
    public bool $isTranslatable = false;

    /**
     * Indicates whether the package supports JSON translations.
     *
     * @var bool
     */
    public bool $loadJsonTranslate = false;

    /**
     * The path to the translation files.
     *
     * @var string|null
     */
    protected string|null $translationPath = null;

    public bool $isView = false;
    protected string|null $viewsPath = null;
    public bool $isViewComponent = false;
    /**
     * @var array<string, object>
     */
    protected array $viewComponents = [];

    /**
     * Set the name of the package.
     *
     * @param string $name The name of the package
     * @return static
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the short name of the package.
     *
     * @return string
     */
    public function shortName(): string
    {
        return $this->shortName ??= Str::kebab($this->name);
    }

    /**
     * Set a custom short name for the package.
     *
     * @param string $shortName The short name to set
     * @return static
     * @throws InvalidArgumentException If the provided short name is not in the expected format
     */
    public function hasShortName(string $shortName): static
    {
        if ($shortName !== Str::kebab($shortName)) {
            throw new InvalidArgumentException(
                "The given namespace [$shortName] does not match the expected format."
            );
        }

        $this->shortName = $shortName;

        return $this;
    }

    /**
     * Get the configuration files.
     *
     * @return string[]|SplFileInfo[]|null
     */
    public function configFiles(): array|null
    {
        return $this->configFiles;
    }

    /**
     * Set or validate configuration files.
     *
     * @param string[]|string|null $configFiles The configuration files to validate
     * @param string $directory The directory name where the configuration files are located
     * @return static
     * @throws Exception If the directory does not exist
     */
    public function hasConfig(
        string|array|null $configFiles = null,
        string            $directory = 'config'
    ): static
    {
        /** @var array<string|SplFileInfo> $configFilesInfo */
        $configFilesInfo = [];

        if (!empty($configFiles)) {
            if (!is_array($configFiles)) {
                $configFiles = [$configFiles];
            }

            foreach ($configFiles as $configFile) {
                $filePath = $this->resolveFilePath($configFile, $directory);

                if (empty($filePath) && !is_file($filePath)) {
                    throw PackagerException::fileNotExist(
                        $configFile,
                        'config'
                    );
                }

                $configFilesInfo[] = $this->getFileInfo(
                    $this->path($configFile)
                );
            }

            /** @var array<string|SplFileInfo> $configFilesInfo */
            $this->configFiles = $configFilesInfo;
        } else {
            $this->configFiles = $this->autoloadFiles($directory);
        }

        $this->isConfigurable = true;

        return $this;
    }

    /**
     * Get the route files.
     *
     * @return string[]|SplFileInfo[]|null
     */
    public function routeFiles(): array|null
    {
        return $this->routeFiles;
    }

    /**
     * Set or validate route files.
     *
     * @param string[]|null $routeFiles The route files to validate
     * @param string $directory The directory name where the route files are located
     * @return static
     * @throws Exception If the directory does not exist
     */
    public function hasRoutes(
        array|string|null $routeFiles = null,
        string            $directory = 'routes'
    ): static
    {
        /** @var array<string|SplFileInfo> $routeFilesInfo */
        $routeFilesInfo = [];

        if (!empty($routeFiles)) {
            if (!is_array($routeFiles)) {
                $routeFiles = [$routeFiles];
            }

            foreach ($routeFiles as $routeFile) {
                $filePath = $this->resolveFilePath($routeFile, $directory);

                if (empty($filePath) && !is_file($filePath)) {
                    throw PackagerException::fileNotExist($routeFile, 'route');
                }

                $routeFilesInfo[] = $this->getFileInfo($filePath);
            }

            /** @var array<string|SplFileInfo> $routeFilesInfo */
            $this->routeFiles = $routeFilesInfo;
        } else {
            $this->routeFiles = $this->autoloadFiles($directory);
        }

        $this->isRoutable = true;

        return $this;
    }

    /**
     * Get the migration files.
     *
     * @return string[]|SplFileInfo[]|null
     */
    public function migrationFiles(): array|null
    {
        return $this->migrationFiles;
    }

    /**
     * Set or validate migration files.
     *
     * @param array<string>|null $migrationFiles The migration files to validate
     * @param string $directory The directory name where the migration files are located
     * @return static
     * @throws PackagerException If the migration file does not exist
     * @throws Exception If any other error occurs
     */
    public function hasMigrations(
        array|null $migrationFiles = null,
        string     $directory = 'database/migrations'
    ): static
    {
        /** @var array<string|SplFileInfo> $migrationFilesInfo */
        $migrationFilesInfo = [];

        if (!empty($this->migrationFiles)) {
            if (!is_array($migrationFiles)) {
                $migrationFiles = [$migrationFiles];
            }

            foreach ($migrationFiles as $migrationFile) {
                if (!file_exists($migrationFile)) {
                    throw PackagerException::fileNotExist(
                        file: $migrationFile,
                        type: 'migration'
                    );
                }

                $migrationFilesInfo[] = $this->getFileInfo(
                    $this->path($migrationFile)
                );
            }

            /** @var array<string|SplFileInfo> $migrationFilesInfo */
            $this->migrationFiles = $migrationFilesInfo;
        } else {
            $this->migrationFiles = $this->autoloadFiles($directory);
        }

        $this->isMigratable = true;

        return $this;
    }

    /**
     * Get the path to the translation files.
     *
     * @return string
     */
    public function translationPath(): string
    {
        return $this->translationPath;
    }

    /**
     * Set or validate translation files.
     *
     * @param string $translationPath The path to the translation files
     * @return static
     * @throws PackagerException If the translation folder does not exist or is invalid
     */
    public function hasTranslations(string $translationPath = '../lang'): static
    {
        $path = $this->path($translationPath);
        if (!File::isDirectory($path)) {
            throw PackagerException::directoryNotFound($path);
        }

        if (File::isEmptyDirectory($path)) {
            throw PackagerException::directoryIsEmpty($path);
        }

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() == 'json') {
                $this->loadJsonTranslate = true;
            }
        }

        foreach (File::directories($path) as $directory) {
            if (!Language::codes()->search(Str::afterLast($directory, '/'))) {
                throw PackagerException::invalidNameLanguageDirectory(
                    $directory
                );
            }
        }

        $this->translationPath = $path;
        $this->isTranslatable = true;

        return $this;
    }

    public function views(): string
    {
        return $this->viewsPath;
    }

    /**
     * Set or variable views folder
     *
     * @param string|null $viewsPath The path to the views files
     * @param string $directory The directory name where the views files are located
     * @throws PackagerException
     */
    public function hasViews(
        string|null $viewsPath = null,
        string      $directory = '../resources/views'
    ): static
    {
        if (!empty($viewsPath)) {
            if (File::isDirectory($this->path($viewsPath))) {
                $this->viewsPath = $this->path($viewsPath);
            } else {
                throw PackagerException::directoryNotFound($this->viewsPath);
            }
        } else {
            $this->viewsPath = $this->path($directory);
        }

        $this->isView = true;
        return $this;
    }

    /**
     * Get the view components registered in the package.
     *
     * @return array<string, object> Array of view components.
     */
    public function viewComponents(): array
    {
        return $this->viewComponents;
    }

    /**
     * Set view components for the package.
     *
     * Validates and registers the given view components.
     *
     * @param array<string, object> $components Array of view components with names as keys and component objects as
     *     values.
     * @return static
     * @throws PackagerException If validation fails for any component.
     */
    public function hasComponents(array $components): static
    {
        if (!empty($components)) {
            if ($this->validateComponents($components)) {
                $this->viewComponents = $components;
                $this->isViewComponent = true;
            }
        }

        return $this;
    }

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
     * @return SplFileInfo[]|string[]
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
     * @return void
     * @throws Exception If the folder does not exist
     */
    private function validFolder(string $path): void
    {
        $realPath = realpath($path);

        if ($realPath === false or !is_dir($realPath)) {
            throw PackagerException::directoryNotFound($path);
        }
    }

    /**
     * Autoload files from the specified path.
     *
     * @param string $path The path to autoload files from
     * @return SplFileInfo[]|String[]
     * @throws Exception If the folder does not exist
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
     * Validates an array of components by checking their names and classes.
     *
     * This method iterates through each component in the provided array, ensuring that:
     *  - Each key (component name) is a string.
     *  - Each component value is an object.
     *
     * If any component fails these validations, a `PackagerException` is thrown with a
     * detailed error message.
     *
     * @param array $components Associative array of components where the key is the component
     *     name and the value is the component class object.
     *
     * @return bool Returns true if all components pass validation.
     *
     * @throws PackagerException If a component name is not a string or if the component value is not an object.
     */
    public function validateComponents(array $components): bool
    {
        foreach ($components as $name => $component) {
            if (!is_string($name)) {
                throw PackagerException::invalidComponentName($name);
            }

            if (!is_object($component)) {
                throw PackagerException::invalidComponentClass(
                    $name,
                    $component
                );
            }
        }

        return true;
    }

    public function isVersion(): string
    {
        return '';
    }
}
