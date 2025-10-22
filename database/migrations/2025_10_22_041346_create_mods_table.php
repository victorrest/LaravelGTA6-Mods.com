<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt')->nullable();
            $table->longText('description');
            $table->string('version')->default('1.0.0');
            $table->string('hero_image_path')->nullable();
            $table->string('download_url');
            $table->decimal('file_size', 8, 2)->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('downloads')->default(0);
            $table->boolean('featured')->default(false);
            $table->string('status')->default('published');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mods');
    }
};
