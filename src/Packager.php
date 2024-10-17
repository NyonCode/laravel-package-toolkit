<?php declare(strict_types=1);

namespace NyonCode\LaravelPackageBuilder;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use NyonCode\LaravelPackageBuilder\Exceptions\PackagerException;
use NyonCode\LaravelPackageBuilder\Support\Enums\Language;
use stdClass;

class Packager
{
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
     * @var string[]|stdClass[]|null
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
     * @var string[]|stdClass[]|null
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
     * @var string[]|stdClass[]|null
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
        if($shortName !== Str::kebab($shortName)){
            throw new InvalidArgumentException("The given namespace [$shortName] does not match the expected format.");
        }

        $this->shortName = $shortName;

        return $this;
    }

    /**
     * Get the configuration files.
     *
     * @return string[]|stdClass[]|null
     */
    public function configFiles(): array|null
    {
        return $this->configFiles;
    }

    /**
     * Set or validate configuration files.
     *
     * @param string[]|string|null $configFiles The configuration files to validate
     * @param string $dirName The directory name where the configuration files are located
     * @return static
     * @throws Exception If the directory does not exist
     */
    public function hasConfig(string|array|null $configFiles = null, string $dirName = 'config'): static
    {
        if (!empty($configFiles)) {
            if (!is_array($configFiles)) {
                $configFiles = [$configFiles];
            }

            foreach ($configFiles as $configFile) {
                if(!is_file($this->path($configFile))) {
                    throw PackagerException::fileNotExist($configFile, 'config');
                }

                $configFilesInfo[] = $this->getFileInfo($this->path($configFile));
            }

            /** @var array<string|stdClass> $configFilesInfo */
            $this->configFiles = $configFilesInfo;

        } else {
            $this->configFiles = $this->autoloadFiles($dirName);
        }

        $this->isConfigurable = true;

        return $this;
    }

    /**
     * Get the route files.
     *
     * @return string[]|stdClass[]|null
     */
    public function routeFiles(): array|null
    {
        return $this->routeFiles;
    }

    /**
     * Set or validate route files.
     *
     * @param string[]|null $routeFiles The route files to validate
     * @param string $dirName The directory name where the route files are located
     * @return static
     * @throws Exception If the directory does not exist
     */
    public function hasRoutes(array|string|null $routeFiles = null, string $dirName = 'routes'): static
    {
        if (!empty($routeFiles)) {
            if(!is_array($routeFiles)) {
                $routeFiles = [$routeFiles];
            }

            foreach ($routeFiles as $routeFile) {
                if(!is_file($this->path($routeFile))) {
                    throw PackagerException::fileNotExist($routeFile, 'route');
                }

                $routeFilesInfo[] = $this->getFileInfo($this->path($routeFile));
            }

            /** @var array<string|stdClass> $routeFilesInfo */
            $this->routeFiles = $routeFilesInfo;

        } else {
            $this->routeFiles = $this->autoloadFiles($dirName);
        }

        $this->isRoutable = true;

        return $this;
    }

    /**
     * Get the migration files.
     *
     * @return string[]|stdClass[]|null
     */
    public function migrationFiles(): array|null
    {
        return $this->migrationFiles;
    }

    /**
     * Set or validate migration files.
     *
     * @param array|null $migrationFiles The migration files to validate
     * @param string $dirName The directory name where the migration files are located
     * @return static
     * @throws PackagerException If the migration file does not exist
     * @throws Exception If any other error occurs
     */
    public function hasMigrations(array|null $migrationFiles = null, string $dirName = 'database/migrations'): static
    {
        if (!empty($this->migrationFiles)) {
            if(!is_array($migrationFiles)) {
                $migrationFiles = [$migrationFiles];
            }

            foreach ($migrationFiles as $migrationFile) {
                if(!file_exists($migrationFile)) {
                    throw PackagerException::fileNotExist($migrationFile, 'migration');
                }

                $migrationFilesInfo[] = $this->getFileInfo($this->path($migrationFile));
            }

            /** @var array<string|stdClass> $migrationFilesInfo */
            $this->migrationFiles = $migrationFilesInfo;

        } else {
            $this->migrationFiles = $this->autoloadFiles($dirName);
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
            throw PackagerException::folderNotExist($path);
        }

        if(File::isEmptyDirectory($path)) {
            throw PackagerException::folderIsEmpty($path);
        }

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() == 'json') {
                $this->loadJsonTranslate = true;
            }
        }

        foreach (File::directories($path) as $directory) {
            if (!Language::codes()->search(Str::afterLast($directory, '/'))) {
                throw PackagerException::invalidNameLanguageDirectory($directory);
            }
        }

        $this->translationPath = $path;
        $this->isTranslatable = true;

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
        if (Str::contains($basePath, "src/Providers")) {
            return $this->basePath = Str::before($basePath, "/Providers");
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
        return $this->basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Get files from a given path.
     *
     * @param string $path The path to search for files
     * @return stdClass[]|string[]
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
     * Get file information as stdClass object.
     *
     * @param string $filePath The path to the file
     * @return stdClass
     */
    private function getFileInfo(string $filePath): stdClass
    {
        $file = new stdClass();

        foreach (pathinfo($filePath) as $key => $value) {
            $file->$key = $value;
        }

        return $file;
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

        if ($realPath === false OR ! is_dir($realPath)) {
            throw PackagerException::folderNotExist($path);
        }
    }

    /**
     * Autoload files from the specified path.
     *
     * @param string $path The path to autoload files from
     * @return stdClass[]|String[]
     * @throws Exception If the folder does not exist
     */
    private function autoloadFiles(string $path): array
    {
        $this->validFolder($this->path("../$path"));
        return $this->getFiles("../$path");
    }
}
