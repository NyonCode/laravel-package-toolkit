<?php

use NyonCode\LaravelPackageToolkit\Support\Runtime;
use Symfony\Component\Process\Process;

afterEach(function () {
    unset($_SERVER['LARAVEL_OCTANE'], $_ENV['LARAVEL_OCTANE']);
    putenv('LARAVEL_OCTANE');
});

it('is classic without a worker in the environment', function () {
    expect(Runtime::detect())->toBe(Runtime::Classic)
        ->and(Runtime::detect()->isLongLived())->toBeFalse()
        ->and(Runtime::server())->toBeNull();
});

it('is octane wherever the SAPI put the variable', function (string $where) {
    match ($where) {
        '$_SERVER' => $_SERVER['LARAVEL_OCTANE'] = '1',
        '$_ENV' => $_ENV['LARAVEL_OCTANE'] = 1,
        'getenv' => putenv('LARAVEL_OCTANE=1'),
    };

    expect(Runtime::detect())->toBe(Runtime::Octane)
        ->and(Runtime::detect()->isLongLived())->toBeTrue();
})->with(['$_SERVER', '$_ENV', 'getenv']);

it('reads a falsy value as not set', function (mixed $value) {
    $_SERVER['LARAVEL_OCTANE'] = $value;

    expect(Runtime::detect())->toBe(Runtime::Classic);
})->with(['', '0', 'false', 0]);

it('names the server behind an octane worker', function (array $environment, ?string $server) {
    $_SERVER['LARAVEL_OCTANE'] = '1';

    foreach ($environment as $key => $value) {
        $_SERVER[$key] = $value;
    }

    try {
        expect(Runtime::server())->toBe($server);
    } finally {
        foreach ($environment as $key => $value) {
            unset($_SERVER[$key]);
        }
    }
})->with([
    'roadrunner' => [['RR_MODE' => 'http'], 'roadrunner'],
    'frankenphp' => [['FRANKENPHP_WORKER' => '1'], 'frankenphp'],
    'unknown' => [[], extension_loaded('swoole') || extension_loaded('openswoole') ? 'swoole' : null],
]);

it('is a frankenphp worker only when the worker function exists too', function () {
    $_SERVER['FRANKENPHP_WORKER'] = '1';

    try {
        expect(Runtime::detect())->toBe(function_exists('frankenphp_handle_request')
            ? Runtime::FrankenPhpWorker
            : Runtime::Classic);
    } finally {
        unset($_SERVER['FRANKENPHP_WORKER']);
    }
});

it('detects a frankenphp worker in a process that has the worker function', function () {
    $script = sprintf(<<<'PHP'
        require %s;
        function frankenphp_handle_request(callable $handler): bool { return false; }
        $_SERVER['FRANKENPHP_WORKER'] = '1';
        echo NyonCode\LaravelPackageToolkit\Support\Runtime::detect()->value, ' ', NyonCode\LaravelPackageToolkit\Support\Runtime::server();
        PHP, var_export(__DIR__.'/../../../vendor/autoload.php', true));

    // An argument list, not a command line: no shell quoting to get wrong on Windows.
    // Errors go to stderr, so a deprecation from an old dependency under prefer-lowest
    // does not end up in the output compared below.
    $process = new Process([PHP_BINARY, '-d', 'display_errors=stderr', '-r', $script]);
    $process->mustRun();

    expect($process->getOutput())->toBe('frankenphp-worker frankenphp');
});
