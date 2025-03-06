<?php

namespace NyonCode\LaravelPackageToolkit\Support\Concerns;

trait HasEnvironmentChecks
{
    /**
     * Checks if the application is running in a production environment.
     *
     * This method uses the `environment` method of the application instance
     * to check if the environment is set to 'production'. If it is, the method
     * returns `true`, otherwise it returns `false`.
     *
     * @return bool Whether the application is running in a production environment.
     */
    protected function isInProduction(): bool
    {
        if(app()->environment('production')){
            return true;
        };

        return false;
    }

    /**
     * Checks if the application is running in a local environment.
     *
     * This method uses the `environment` method of the application instance
     * to check if the environment is set to 'local'. If it is, the method
     * returns `true`, otherwise it returns `false`.
     *
     * @return bool Whether the application is running in a local environment.
     */
    protected function isInLocal(): bool
    {
        if(app()->environment('local')){
            return true;
        };

        return false;
    }
}