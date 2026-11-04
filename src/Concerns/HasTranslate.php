<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

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
        if (! is_dir($path)) {
            throw new DirectoryNotFoundException(
                "Directory [$path] does not exist"
            );
        }

        // Read with scandir, not Symfony Finder: this runs in register() of every
        // request, where three Finder walks of lang/ cost more than the rest of it.
        $entries = $this->directoryEntries($path);

        if ($entries === []) {
            return $this;
        }

        $this->loadJsonTranslate = $this->containsJsonFile($path, $entries);
        $languages = null;

        foreach ($entries as $entry) {
            if (! is_dir($path.DIRECTORY_SEPARATOR.$entry)) {
                continue;
            }

            // Support region locales (e.g. pt_BR, en-US) by validating the language part only
            $languageCode = Str::lower(Str::before(Str::before($entry, '-'), '_'));
            $languages ??= array_flip(Language::codes()->all());

            if (! isset($languages[$languageCode])) {
                throw new InvalidLanguageDirectoryException(
                    'Invalid language directory ['.$path.DIRECTORY_SEPARATOR.$entry.']. Directory name must be one of the supported languages.'
                );
            }
        }

        $this->translationPath = $path;
        $this->isTranslatable = true;

        return $this;
    }

    /**
     * Names in a directory, without the dot entries.
     *
     * @return list<string>
     */
    private function directoryEntries(string $path): array
    {
        $entries = @scandir($path);

        return $entries === false ? [] : array_values(array_diff($entries, ['.', '..']));
    }

    /**
     * Whether a JSON translation lies anywhere under the directory.
     *
     * @param  list<string>  $entries
     */
    private function containsJsonFile(string $path, array $entries): bool
    {
        foreach ($entries as $entry) {
            $full = $path.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($full)) {
                if ($this->containsJsonFile($full, $this->directoryEntries($full))) {
                    return true;
                }
            } elseif (str_ends_with(Str::lower($entry), '.json')) {
                return true;
            }
        }

        return false;
    }
}
