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
     *
     * @throws InvalidLanguageDirectoryException
     *
     * @return static
     */
    public function hasTranslations(string $translationPath = '../lang'): static
    {
        $path = $this->path($translationPath);
        if (!File::isDirectory($path)) {
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
            if (!Language::codes()->search(Str::afterLast($directory, '/'))) {
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