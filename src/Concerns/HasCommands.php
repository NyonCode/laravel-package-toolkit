<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use NyonCode\LaravelPackageToolkit\Support\Concerns\HasNamespaceResolver;

trait HasCommands
{
    use FilesResolver;
    use HasNamespaceResolver;

    /**
     * Whether the package has commands
     *
     * @var bool
     */
    protected bool $isCommandable = false;

    /**
     * The commands
     *
     * @var string[]
     */
    public array $commands = [];

    /**
     * Check if the package has commands
     *
     * @return bool
     */
    public function isCommandable(): bool
    {
        return $this->isCommandable;
    }

    /**
     * Registers the package's commands.
     *
     * Can accept either an array of commands, a single command as a string or an object that implements
     * the `Illuminate\Console\Command` interface.
     *
     * @param string|array<string> $commandsClass The commands to register.
     * @param string $directory The directory name where the commands are located
     *
     * @throws Exception
     *
     * @return static
     */
    public function hasCommands(string|array|null $commandsClass = null, string $directory = 'Commands'): static
    {
        if(empty($commandsClass)) {
            $files = $this->autoloadFiles($directory);

            foreach ($files as $file) {
                $commandsClass[] = $this->getNamespaceFromPath($file->getPathname()) . '\\' . $file->getBaseFileName();
            }
        }
        if(!is_array($commandsClass)) {
            $this->commands[] = $commandsClass;
        } else {
            $this->commands = array_merge($this->commands, $commandsClass);
        }

        if(!empty($this->commands)) {
            $this->isCommandable = true;
        }

        return $this;
    }

    /**
     * Registers a single package command.
     *
     * @param string $commandClass The command to register.
     *
     * @throws FileNotFoundException|Exception
     *
     * @return static
     */
    public function hasCommand(string $commandClass): static
    {
        return $this->hasCommands($commandClass);
    }
}