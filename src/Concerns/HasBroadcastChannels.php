<?php

namespace NyonCode\LaravelPackageToolkit\Concerns;

use Exception;
use NyonCode\LaravelPackageToolkit\Support\SplFileInfo;

trait HasBroadcastChannels
{
    use FilesResolver;

    /**
     * @var bool Indicates whether the package has broadcast channel files.
     */
    private bool $isBroadcastable = false;

    /**
     * @var SplFileInfo[] The broadcast channel files for the package.
     */
    protected array $broadcastChannelFiles = [];

    /**
     * Indicates whether the package is broadcastable.
     */
    public function isBroadcastable(): bool
    {
        return $this->isBroadcastable;
    }

    /**
     * Get the broadcast channel files.
     *
     * @return SplFileInfo[]
     */
    public function broadcastChannelFiles(): array
    {
        return $this->broadcastChannelFiles;
    }

    /**
     * Set or validate broadcast channel files.
     *
     * A channel file authorizes broadcast channels with `Broadcast::channel()`. Until now
     * it had to be smuggled through `hasRoutes()`, which loads it into the router inside a
     * route group — the wrong destination for a channel authorization callback.
     *
     * Channels are a load-only resource and are deliberately not publishable: an
     * application does not load `routes/channels.php` unless its own bootstrap asks for
     * it, so a published copy would look authoritative while the package kept using its
     * own. Consumers override authorization by re-registering the channel from their
     * application instead.
     *
     * @param  array<string>|string|null  $channelFiles  The channel files to validate
     * @param  string  $directory  The directory name where the channel files are located
     *
     * @throws Exception If any channel file does not exist
     */
    public function hasBroadcastChannels(
        array|string|null $channelFiles = null,
        string $directory = '../routes'
    ): static {
        $this->broadcastChannelFiles = $this->resolveFiles(
            files: $channelFiles,
            directory: $directory,
            type: 'broadcast channel'
        );

        if (! empty($this->broadcastChannelFiles)) {
            $this->isBroadcastable = true;
        }

        return $this;
    }
}
