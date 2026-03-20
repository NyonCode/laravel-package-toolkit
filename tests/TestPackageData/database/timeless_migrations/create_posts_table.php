<?php

namespace NyonCode\LaravelPackageToolkit\Tests\TestPackageData\database\timeless_migrations;

/**
 * Class create_posts_table.php
 *
 * @project   laravel-package-toolkit
 *
 * @author    Ondřej Nyklíček
 *
 * @created   20.03.2026
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
