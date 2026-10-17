<?php

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

use Illuminate\View\Compilers\BladeCompiler;

trait BladeComponentLoader
{
    /**
     * Load the view components registered in the package.
     *
     * This method uses the components registered in the packager and registers them
     * with Blade.
     *
     * @param  list<array{component: class-string, alias: string|null, prefix: string}>  $components
     */
    protected function loadViewComponents(array $components): void
    {
        $this->callAfterResolving(BladeCompiler::class, function (
            BladeCompiler $blade
        ) use ($components) {
            foreach ($components as $config) {
                $blade->component(
                    $config['component'],
                    $config['alias'],
                    $config['prefix']
                );
            }
        });
    }

    /**
     * Register the view component namespaces for the package.
     *
     * This method takes an associative array of namespace prefixes and namespaces
     * and registers them with Blade.
     *
     * @param  array<string, string>  $namespaces  An associative array of namespace prefixes and namespaces.
     */
    protected function loadViewComponentNamespaces(array $namespaces): void
    {
        $this->callAfterResolving(BladeCompiler::class, function (
            BladeCompiler $blade
        ) use ($namespaces) {
            foreach ($namespaces as $prefix => $namespace) {
                $blade->componentNamespace($namespace, $prefix);
            }
        });
    }
}
