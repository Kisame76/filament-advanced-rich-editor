<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents kept as a tree in a column of the database's own JSON type, which is what a field
 * with `->json()` is usually given. On SQLite that is text; on Postgres it is a type `LIKE`
 * does not work on until the column is read as text, and on MySQL one it converts first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tree_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->json('content')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tree_posts');
    }
};
