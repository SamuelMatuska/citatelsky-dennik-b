<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->unsignedSmallInteger('publish_year');
            $table->string('genre', 40);
            $table->unsignedInteger('pages');
            $table->string('isbn', 13)->nullable();
            $table->string('status', 10)->default('unread');
            $table->unsignedInteger('pages_read')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
