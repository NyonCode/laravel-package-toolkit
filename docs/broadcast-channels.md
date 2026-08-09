---
title: Broadcast channels
description: Register channel authorization callbacks with the broadcaster, without smuggling them through a route file.
---

# Broadcast channels

```php
public function hasBroadcastChannels(
    array|string|null $channelFiles = null,
    string $directory = '../routes',
): static
```

Added in **2.4.0**. A channel file is the one that calls `Broadcast::channel()` to authorize private
and presence channels. Before this existed, the only way to ship one from a package was to pass it
to `hasRoutes()` — which loads it through `loadRoutesFrom()`, inside a route group. That is the
wrong destination for an authorization callback, and it put your channel names into the router.

```php title="src/BlogServiceProvider.php"
$packager
    ->name('Blog')
    ->hasBroadcastChannels();
```

Each declared file is `require`d at boot, so its `Broadcast::channel()` calls run against the
application's broadcaster.

## Writing the channel file

```php title="routes/channels.php"
use Acme\Blog\Models\Post;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('blog.post.{postId}', function ($user, int $postId) {
    return Post::find($postId)?->isVisibleTo($user) ?? false;
});

Broadcast::channel('blog.presence.{postId}', function ($user, int $postId) {
    return ['id' => $user->id, 'name' => $user->name];
});
```

Prefix your channel names with the package short name. Channel names are a flat global namespace
shared with the application and every other package — `post.{id}` will collide sooner or later.

## Keep channels out of the routes directory

The default directory is `../routes`, matching Laravel's own convention. That creates a trap:

```php
$packager
    ->name('Blog')
    ->hasRoutes()               // ← discovers routes/channels.php too [tl! ~~]
    ->hasBroadcastChannels();   // ← and so does this [tl! ~~]
```

Both builders discover the same directory, so `channels.php` is loaded twice — once correctly, once
as a route file. Two ways out, both fine. Name the files explicitly:

```php
$packager
    ->hasRoutes()                                // [tl! --]
    ->hasBroadcastChannels();                    // [tl! --]
    ->hasRoutes(['web.php', 'api.php'])          // [tl! ++]
    ->hasBroadcastChannels(['channels.php']);    // [tl! ++]
```

Or give channels their own directory, and let discovery keep working:

```php
$packager
    ->hasRoutes()
    ->hasBroadcastChannels(directory: '../broadcasting');   // [tl! focus]
```

## Several channel files

```php
$packager->hasBroadcastChannels(
    ['channels.php', 'presence-channels.php'],
    '../broadcasting',
);
```

All of them are required, in order.

## Applications without broadcasting

The toolkit requires `illuminate/support`, not `illuminate/broadcasting`. If the broadcasting
component is not installed, `bootBroadcastChannels()` checks for `BroadcastManager` and returns
early — no exception, no fatal error. Your package stays installable in an application that does not
broadcast.

```php
if (! class_exists(BroadcastManager::class)) {
    return $this;
}
```

## Channels are deliberately not publishable

There is no `blog::channels` tag, and running `vendor:publish --tag=blog::channels` publishes
nothing:

```bash
php artisan vendor:publish --tag=blog::channels
# Exits 0, writes nothing.
```

That is a design decision, not an omission. An application does not load `routes/channels.php`
unless its own bootstrap asks for it, so a published copy would sit there looking authoritative
while the package quietly kept using its own — the worst possible outcome for a file whose entire
job is authorization.

**Consumers override a channel by re-registering it from their own application.** The last
registration for a channel name wins, and application providers boot after package providers:

```php title="app/Providers/AppServiceProvider.php"
public function boot(): void
{
    Broadcast::channel('blog.post.{postId}', function ($user, int $postId) {
        return $user->isAdmin() || Post::find($postId)?->isVisibleTo($user);
    });
}
```

Document the channel names your package registers, the way you would document a public API — that
list *is* the extension point.

## Using the channels

From your package's events:

```php title="src/Events/PostPublished.php"
namespace Acme\Blog\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PostPublished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Post $post) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("blog.post.{$this->post->id}")];
    }

    public function broadcastAs(): string
    {
        return 'blog.post.published';
    }
}
```

And from the consumer's JavaScript:

```js
Echo.private(`blog.post.${postId}`)
    .listen('.blog.post.published', (event) => {
        console.log(event.post)
    })
```

## Testing

```php
use Illuminate\Support\Facades\Broadcast;

test('the package registers its channels', function () {
    $channels = Broadcast::getChannels()->keys()->all();

    expect($channels)->toContain('blog.post.{postId}')
        ->and($channels)->toContain('blog.presence.{postId}');
});

test('channel files are not registered as routes', function () {
    expect(collect(app('router')->getRoutes()->getRoutes())
        ->contains(fn ($route) => str_contains($route->uri(), 'blog.post')))
        ->toBeFalse();
});
```

## Introspection

```php
$packager->isBroadcastable();       // bool
$packager->broadcastChannelFiles(); // Support\SplFileInfo[]
```
