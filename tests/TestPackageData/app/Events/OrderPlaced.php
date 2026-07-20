<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\app\Events;

class OrderPlaced
{
    public function __construct(public string $id = '') {}
}
