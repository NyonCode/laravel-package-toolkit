<?php

declare(strict_types=1);

namespace NyonCode\LaravelPackageToolkit\Support;

/**
 * The kind of process the application is running in.
 *
 * Only one distinction matters to the toolkit: whether the process serves one request and
 * dies, or boots once and serves many. Everything a provider does in `register()` and
 * `boot()` happens once per process, so under PHP-FPM it happens once per request and
 * under a long-lived worker once per worker — and state that a request leaves behind is
 * either thrown away with the process or handed to the next request.
 *
 * Detected from the environment rather than from what is installed: an application can
 * have `laravel/octane` installed and still serve half its routes through PHP-FPM, and
 * the FPM half must behave exactly as it always did.
 */
enum Runtime: string
{
    /** PHP-FPM, CGI, the built-in server, an artisan command. */
    case Classic = 'classic';

    /** A Laravel Octane worker — Swoole, Open Swoole, RoadRunner or FrankenPHP. */
    case Octane = 'octane';

    /** A FrankenPHP worker script that is not Octane's: nothing dispatches Octane's events. */
    case FrankenPhpWorker = 'frankenphp-worker';

    /**
     * Detect the runtime of the current process.
     *
     * `LARAVEL_OCTANE` is what all three of Octane's `octane:start` commands put in the
     * worker's environment, and what the framework itself tests to know it is under
     * Octane. It is read from every place a SAPI may have put it, because which of
     * `$_SERVER`, `$_ENV` and `getenv()` carries a process variable depends on the SAPI
     * and on `variables_order`.
     */
    public static function detect(): self
    {
        if (self::environment('LARAVEL_OCTANE')) {
            return self::Octane;
        }

        if (self::environment('FRANKENPHP_WORKER') && function_exists('frankenphp_handle_request')) {
            return self::FrankenPhpWorker;
        }

        return self::Classic;
    }

    /**
     * Whether one booted application serves more than one request.
     */
    public function isLongLived(): bool
    {
        return $this !== self::Classic;
    }

    /**
     * The application server behind an Octane worker, as far as the process can tell.
     *
     * Best effort and diagnostic only — nothing in the toolkit behaves differently per
     * server, because Octane hands all three the same worker and the same events.
     */
    public static function server(): ?string
    {
        if (self::detect() === self::Classic) {
            return null;
        }

        if (self::environment('FRANKENPHP_WORKER') || function_exists('frankenphp_handle_request')) {
            return 'frankenphp';
        }

        if (self::environment('RR_MODE')) {
            return 'roadrunner';
        }

        if (extension_loaded('swoole') || extension_loaded('openswoole')) {
            return 'swoole';
        }

        return null;
    }

    private static function environment(string $key): bool
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return ! in_array($value, [false, null, '', '0', 0, 'false'], true);
    }
}
