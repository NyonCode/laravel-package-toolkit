<?php

namespace NyonCode\LaravelPackageToolkit\Tests\Unit\Support\Worker\Fixture {
    trait CountsCalls
    {
        public static int $calls = 0;
    }

    class Base
    {
        public static array $registry = [];
    }

    class Child extends Base
    {
        use CountsCalls;

        public static string $label = 'declared';
    }

    class Uninitialized
    {
        public static ?\stdClass $holder;
    }

    class Service {}
}

namespace NyonCode\LaravelPackageToolkit\Tests\Unit\Support\Worker {

    use Illuminate\Foundation\Application;
    use NyonCode\LaravelPackageToolkit\Support\Worker\PackageIsolation;
    use NyonCode\LaravelPackageToolkit\Tests\TestCase;
    use NyonCode\LaravelPackageToolkit\Tests\Unit\Support\Worker\Fixture\Base;
    use NyonCode\LaravelPackageToolkit\Tests\Unit\Support\Worker\Fixture\Child;
    use NyonCode\LaravelPackageToolkit\Tests\Unit\Support\Worker\Fixture\Service;
    use NyonCode\LaravelPackageToolkit\Tests\Unit\Support\Worker\Fixture\Uninitialized;

    uses(TestCase::class);

    const FIXTURES = 'NyonCode\\LaravelPackageToolkit\\Tests\\Unit\\Support\\Worker\\Fixture\\';

    beforeEach(function () {
        Base::$registry = [];
        Child::$label = 'declared';
        Child::$calls = 0;
        class_exists(Uninitialized::class);
        $this->worker = new Application(sys_get_temp_dir());
    });

    test('restoring before a snapshot does nothing', function () {
        $isolation = new PackageIsolation('fixture', [FIXTURES]);
        Child::$label = 'dirty';
        $this->worker->instance(Service::class, new Service());

        $isolation->restore($this->worker);

        expect(Child::$label)->toBe('dirty')
            ->and($this->worker->resolved(Service::class))->toBeTrue()
            ->and($isolation->hasSnapshot())->toBeFalse();
    });

    test('a static inherited from a parent is restored once, through the parent', function () {
        Base::$registry = ['boot'];
        $isolation = new PackageIsolation('fixture', [FIXTURES]);
        $isolation->snapshot($this->worker);

        Child::$registry[] = 'request';
        $isolation->restore($this->worker);

        expect(Base::$registry)->toBe(['boot']);
    });

    test('a static a class takes from a trait is that class\'s own and is restored', function () {
        $isolation = new PackageIsolation('fixture', [FIXTURES]);
        $isolation->snapshot($this->worker);

        Child::$calls = 7;
        $isolation->restore($this->worker);

        expect(Child::$calls)->toBe(0);
    });

    test('a typed static that was never initialised is left alone', function () {
        $isolation = new PackageIsolation('fixture', [FIXTURES]);
        $isolation->snapshot($this->worker);

        Uninitialized::$holder = new \stdClass();
        $isolation->restore($this->worker);

        // The snapshot recorded it as null — no value — and putting null back is what a
        // nullable typed static without a default can hold.
        expect(Uninitialized::$holder)->toBeNull();
    });

    test('without namespaces only the owned abstracts are forgotten and no static is touched', function () {
        $isolation = new PackageIsolation('fixture', []);
        $isolation->own(['acme.owned']);
        $isolation->snapshot($this->worker);

        $this->worker->instance('acme.owned', new Service());
        $this->worker->instance(Service::class, new Service());
        Child::$label = 'dirty';

        $isolation->restore($this->worker);

        expect($this->worker->resolved('acme.owned'))->toBeFalse()
            ->and($this->worker->resolved(Service::class))->toBeTrue()
            ->and(Child::$label)->toBe('dirty');
    });

    test('an instance that was there after boot survives, one made later does not', function () {
        $kept = new Service();
        $this->worker->instance(Service::class, $kept);
        $isolation = new PackageIsolation('fixture', [FIXTURES]);
        $isolation->snapshot($this->worker);

        $this->worker->instance(FIXTURES.'Later', new Service());
        $isolation->restore($this->worker);

        expect($this->worker->make(Service::class))->toBe($kept)
            ->and($this->worker->bound(FIXTURES.'Later'))->toBeFalse();
    });

    test('an instance outside the package is never touched', function () {
        $isolation = new PackageIsolation('fixture', [FIXTURES]);
        $isolation->snapshot($this->worker);

        $this->worker->instance('someone.else', $other = new \stdClass());
        $isolation->restore($this->worker);

        expect($this->worker->make('someone.else'))->toBe($other);
    });

    test('restoring is idempotent across many operations', function () {
        $isolation = new PackageIsolation('fixture', [FIXTURES]);
        Child::$label = 'boot';
        $isolation->snapshot($this->worker);

        foreach (range(1, 50) as $i) {
            Child::$label = "request-$i";
            Base::$registry[] = $i;
            $isolation->restore($this->worker);
        }

        expect(Child::$label)->toBe('boot')->and(Base::$registry)->toBe([]);
    });
}

namespace NyonCode\LaravelPackageToolkit\Tests\Unit\Support\Worker {

    use Illuminate\Foundation\Application;
    use NyonCode\LaravelPackageToolkit\Support\PublishedAssets;
    use NyonCode\LaravelPackageToolkit\Support\Worker\PackageIsolation;

    test('a namespace that also covers the toolkit leaves the toolkit alone', function () {
        $worker = new Application(sys_get_temp_dir());
        $isolation = new PackageIsolation('wide', ['NyonCode\\']);
        $isolation->snapshot($worker);

        $worker->instance(PublishedAssets::class, $assets = new PublishedAssets());
        $isolation->restore($worker);

        $scanned = array_map(
            fn (array $entry) => $entry[0]->class,
            (new \ReflectionProperty($isolation, 'properties'))->getValue($isolation),
        );
        $toolkitSource = realpath(__DIR__.'/../../../../src');

        $toolkitClasses = array_filter($scanned, fn (string $class) => str_starts_with(
            (string) (new \ReflectionClass($class))->getFileName(), $toolkitSource,
        ));

        expect($worker->make(PublishedAssets::class))->toBe($assets)
            ->and($toolkitClasses)->toBe([]);
    });

    test('a namespace that would cover the framework is ignored', function (string $namespace) {
        $isolation = new PackageIsolation('framework', [$namespace]);

        expect((new \ReflectionProperty($isolation, 'namespaces'))->getValue($isolation))->toBe([]);
    })->with(['Illuminate\\', 'Laravel\\', 'Symfony\\', 'Illuminate\\Support\\']);
}
