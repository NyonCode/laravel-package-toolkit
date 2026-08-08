---
title: Events
description: Register event listeners and subscribers from your package's configuration.
---

# Events

```php
public function hasEvent(string $event, string|Closure|array $listeners): static
public function hasEvents(array $events): static
public function hasSubscriber(string $subscriber): static
public function hasSubscribers(array $subscribers): static
```

Listeners are registered with `Event::listen()` and subscribers with `Event::subscribe()`, at boot.

## A single event

```php title="src/BlogServiceProvider.php"
use Acme\Blog\Events\PostPublished;
use Acme\Blog\Listeners\NotifySubscribers;

$packager
    ->name('Blog')
    ->hasEvent(PostPublished::class, NotifySubscribers::class);
```

Several listeners for one event, in order:

```php
$packager->hasEvent(PostPublished::class, [
    NotifySubscribers::class,
    UpdateSearchIndex::class,
    PingWebhooks::class,
]);
```

## A map of events

```php
$packager->hasEvents([
    PostPublished::class => [
        NotifySubscribers::class,
        UpdateSearchIndex::class,
    ],
    PostDeleted::class => RemoveFromSearchIndex::class,
    CommentPosted::class => [ModerateComment::class],
]);
```

Repeated calls **merge** per event rather than replacing:

```php
$packager
    ->hasEvent(PostPublished::class, NotifySubscribers::class)
    ->hasEvent(PostPublished::class, UpdateSearchIndex::class);

// PostPublished => [NotifySubscribers, UpdateSearchIndex]
```

That makes it safe to add listeners from a
[conditional callback](/conditional-configuration) without clobbering the base set.

## Closures

```php
$packager->hasEvent(PostPublished::class, function (PostPublished $event) {
    logger()->info('Post published', ['id' => $event->post->id]);
});
```

Convenient, but a closure listener cannot be queued and cannot be tested in isolation. Use a class
for anything with behaviour.

## Subscribers

A subscriber declares its own bindings, which keeps a related group of listeners in one file:

```php title="src/Listeners/BlogSubscriber.php"
namespace Acme\Blog\Listeners;

use Acme\Blog\Events\{CommentPosted, PostDeleted, PostPublished};
use Illuminate\Events\Dispatcher;

class BlogSubscriber
{
    public function handlePostPublished(PostPublished $event): void
    {
        // …
    }

    public function handlePostDeleted(PostDeleted $event): void
    {
        // …
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            PostPublished::class => 'handlePostPublished',
            PostDeleted::class => 'handlePostDeleted',
        ];
    }
}
```

```php
$packager
    ->name('Blog')
    ->hasSubscriber(BlogSubscriber::class);

// or several
$packager->hasSubscribers([
    BlogSubscriber::class,
    BlogAnalyticsSubscriber::class,
]);
```

Subscribers accumulate the same way listeners do.

## Listening to *other* packages' events

This is where a package's event registration earns its keep — reacting to the framework or to
another package, without asking the consumer to wire anything:

```php
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Events\MigrationsEnded;

$packager->hasEvents([
    Login::class => RecordAuthorLogin::class,
    MigrationsEnded::class => RebuildBlogSearchIndex::class,
]);
```

Combine with a [conditional check](/conditional-configuration) when the event class belongs to an
optional dependency:

```php
$packager->whenClassExists(\Laravel\Cashier\Events\WebhookReceived::class, function (Packager $p) {
    $p->hasEvent(\Laravel\Cashier\Events\WebhookReceived::class, GrantBlogAccess::class);
});
```

## Writing a listener

```php title="src/Listeners/NotifySubscribers.php"
namespace Acme\Blog\Listeners;

use Acme\Blog\Events\PostPublished;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifySubscribers implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function __construct(private NotificationDispatcher $dispatcher) {}

    public function handle(PostPublished $event): void
    {
        $this->dispatcher->notifyAll($event->post);
    }

    public function failed(PostPublished $event, Throwable $exception): void
    {
        report($exception);
    }
}
```

Queueable listeners work exactly as they do in an application — `ShouldQueue` is honoured by the
dispatcher, not by the registration.

## Wildcards

Laravel's wildcard syntax is passed straight through:

```php
$packager->hasEvent('blog.*', AuditBlogActivity::class);
```

A wildcard listener receives the event name as its first argument and the payload array as its
second.

## Events your package dispatches

Naming and documenting them is the difference between a package that can be extended and one that
has to be forked:

```php title="src/Events/PostPublished.php"
namespace Acme\Blog\Events;

use Acme\Blog\Models\Post;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Post $post) {}
}
```

```php
PostPublished::dispatch($post);
```

## Introspection

```php
$packager->isEventable();  // bool
$packager->events();       // ['Event' => [listener, …]]
$packager->subscribers();  // [Subscriber::class, …]
```

## Testing

```php
use Illuminate\Support\Facades\Event;

test('the package registers its listener', function () {
    expect(Event::hasListeners(PostPublished::class))->toBeTrue();
});

test('publishing a post notifies subscribers', function () {
    Event::fake([PostPublished::class]);

    $post->publish();

    Event::assertDispatched(PostPublished::class, fn ($event) => $event->post->is($post));
});
```

:::note `Event::fake()` and registration
`Event::fake()` swaps the dispatcher, which discards listeners registered before it was called.
Assert on registration in one test and on dispatch in another, rather than trying to do both at
once.
:::
