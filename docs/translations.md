---
title: Translations
description: Ship PHP and JSON translation files, namespaced under your package's short name, with language-code validation built in.
---

# Translations

```php
public function hasTranslations(string $translationPath = 'lang'): static
```

One call registers both styles of Laravel translation file — the keyed PHP arrays and the flat JSON
strings — and makes the directory publishable.

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasTranslations();
```

:::note The argument is a directory name, not a path
`hasTranslations('lang')` resolves to `../lang` relative to your provider's directory. Pass
`'resources/lang'` to get `../resources/lang`. Unlike the other builders, there is no separate
`directory:` parameter — the one argument *is* the directory.
:::

## Directory layout

```text
acme/blog/
└── lang/
    ├── en/
    │   ├── messages.php
    │   └── validation.php
    ├── cs/
    │   └── messages.php
    ├── pt_BR/
    │   └── messages.php
    ├── en.json
    └── cs.json
```

```php title="lang/en/messages.php"
return [
    'title' => 'Blog',
    'post' => [
        'published' => 'Published on :date',
        'by' => 'Written by :author',
    ],
    'posts_count' => '{0} No posts|{1} One post|[2,*] :count posts',
];
```

```json title="lang/en.json"
{
    "Read more": "Read more",
    "Leave a comment": "Leave a comment"
}
```

## Using them

PHP translations are namespaced under the package short name:

```php
trans('blog::messages.title');                                  // 'Blog'
trans('blog::messages.post.published', ['date' => $date]);
trans_choice('blog::messages.posts_count', $posts->count());
```

```blade
<h1>{{ __('blog::messages.title') }}</h1>
<p>{{ __('blog::messages.post.by', ['author' => $post->author->name]) }}</p>
```

JSON translations are **not** namespaced — that is how Laravel's JSON loader works, for packages and
applications alike:

```php
__('Read more');
```

Which means your JSON keys share one global space with the application's. Keep them few, and keep
them specific enough not to collide; anything ambiguous belongs in a namespaced PHP file.

## Language directory validation

Every subdirectory is checked against the toolkit's `Support\Enums\Language` enum — the ISO 639-1
set, 180-odd codes. An unrecognised one throws during registration:

```text
Invalid language directory [/…/lang/english].
Directory name must be one of the supported languages.
```

The check is there because a mistyped locale directory does not fail loudly on its own — Laravel
simply never finds the translations, and the fallback locale is served instead. Failing at
registration turns a silent content bug into an immediate one.

**Region locales are supported.** Only the language part before a `-` or `_` is validated, so
`pt_BR`, `en-US` and `zh_CN` all pass.

```php
use NyonCode\LaravelPackageToolkit\Support\Enums\Language;

Language::codes();      // Collection<string>   ['ab', 'aa', 'af', …]
Language::names();      // Collection<string>   ['Abkhazian', 'Afar', …]
Language::collection(); // Collection<Language>

Language::CS->value;    // 'Czech'
Language::CS->name;     // 'CS'
```

## Missing and empty directories

| Situation | Result |
|---|---|
| Directory does not exist | `DirectoryNotFoundException` |
| Directory exists but is empty | returns silently, package is **not** translatable |
| Subdirectory is not a language code | `InvalidLanguageDirectoryException` |

The empty-directory case is deliberate: a package can commit an empty `lang/` placeholder without
declaring itself translatable, which keeps `isTranslatable()` honest.

## Publishing

```bash
php artisan vendor:publish --tag=blog::translations
```

The whole directory is copied to `lang/vendor/{short-name}/`:

```text
lang/vendor/blog/
├── en/messages.php
├── cs/messages.php
├── en.json
└── cs.json
```

On an application still using the pre-Laravel-9 layout — one where the `lang_path()` helper does not
exist — the destination falls back to `resources/lang/vendor/blog/`. The toolkit checks for the
helper rather than the framework version.

Laravel checks the vendor override directory **before** the package's own, so a consumer who
publishes and edits `lang/vendor/blog/en/messages.php` gets their version. Keys they did not
override still fall through to yours.

## Adding a language as a consumer

Only the file needs to exist — nothing to register:

```text
lang/vendor/blog/de/messages.php
```

```php
app()->setLocale('de');

trans('blog::messages.title');
```

## Translating your own package's output

Everything your package renders should go through the translator, including exception messages and
command output:

```php title="src/Commands/PruneCommand.php"
public function handle(): int
{
    $count = Post::onlyTrashed()->forceDelete();

    $this->info(trans('blog::messages.pruned', ['count' => $count]));

    return self::SUCCESS;
}
```

## Introspection

```php
$packager->isTranslatable();     // bool
$packager->translationPath();    // absolute path to the lang directory
$packager->loadJsonTranslate();  // bool — true if any .json file was found
```

`loadJsonTranslate()` is set by scanning the directory recursively for a `.json` extension, so a
JSON file nested inside a locale directory is detected too.

## Testing

```php
test('the package registers its translations', function () {
    expect(trans('blog::messages.title'))->toBe('Blog');
});

test('the package registers its json translations', function () {
    expect(trans('Read more'))->toBe('Read more');
});

test('translations are publishable', function () {
    $this->artisan('vendor:publish --tag=blog::translations')->assertExitCode(0);

    expect(lang_path('vendor/blog/en/messages.php'))->toBeFile();
});
```
