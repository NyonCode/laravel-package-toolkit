<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

trait HasCommands
{
    use FilesResolver;

    /**
     * Whether the package has commands
     *
     * @var bool
     */
    public bool $isCommandable = false;

    /**
     * The commands
     *
     * @var string[]
     */
    public array $commands = [];

    /**
     * Registers the package's commands.
     *
     * Can accept either an array of commands, a single command as a string or an object that implements
     * the `Illuminate\Console\Command` interface.
     *
     * @param string|array<string> $commandsClass The commands to register.
     * @return static
     */
    public function hasCommands(string|array $commandsClass): static
    {
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

    public function hasCommand(string $commandClass): static
    {
        return $this->hasCommands($commandClass);
    }

}