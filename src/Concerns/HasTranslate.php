<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use NyonCode\LaravelPackageToolkit\Exceptions\InvalidLanguageDirectoryException;
use NyonCode\LaravelPackageToolkit\Support\Enums\Language;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;

trait HasTranslate
{
    use FilesResolver;

    /**
     * @var bool Indicates whether the package is translatable.
     */
    private bool $isTranslatable = false;

    /**
     * @var bool Indicates whether the package supports JSON translations.
     */
    private bool $loadJsonTranslate = false;

    /**
     * @var string The path to the translation files.
     */
    protected string $translationPath = '';

    /**
     * Get the value of isTranslatable
     */
    public function isTranslatable(): bool
    {
        return $this->isTranslatable;
    }

    /**
     * Get the value of loadJsonTranslate
     */
    public function loadJsonTranslate(): bool
    {
        return $this->loadJsonTranslate;
    }

    /**
     * Get the path to the translation files.
     */
    public function translationPath(): string
    {
        return $this->translationPath;
    }

    /**
     * Set or validate translation files.
     *
     * @param  string  $translationPath  The path to the translation files
     *
     * @throws InvalidLanguageDirectoryException
     */
    public function hasTranslations(string $translationPath = 'lang'): static
    {
        $path = $this->path("../$translationPath");
        if (! File::isDirectory($path)) {
            throw new DirectoryNotFoundException(
                "Directory [$path] does not exist"
            );
        }

        if (File::isEmptyDirectory($path)) {
            return $this;
        }

        foreach (File::allFiles($path) as $file) {
            if ($file->getExtension() == 'json') {
                $this->loadJsonTranslate = true;
            }
        }

        foreach (File::directories($path) as $directory) {
            if (! Language::codes()->search(Str::afterLast($directory, DIRECTORY_SEPARATOR))) {
                throw new InvalidLanguageDirectoryException(
                    "Invalid language directory [$directory]. Directory name must be one of the supported languages."
                );
            }
        }

        $this->translationPath = $path;
        $this->isTranslatable = true;

        return $this;
    }
}
