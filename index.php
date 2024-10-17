<?php

require_once "vendor/autoload.php";

use Illuminate\Support\Facades\File;
use NyonCode\LaravelPackageBuilder\Packager;

$test = new Packager();

$files = File::files('/path/to/directory');

$test->setBasePath($test->getPackageBaseDir());

var_dump(File::files($test->getPackageBaseDir()));
