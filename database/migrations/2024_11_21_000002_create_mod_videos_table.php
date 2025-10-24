<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mod_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform')->default('youtube');
            $table->string('external_id');
            $table->string('title');
            $table->string('slug');
            $table->string('thumbnail_path')->nullable();
            $table->string('duration')->nullable();
            $table->string('channel_title')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->json('payload')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['mod_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mod_videos');
    }
};
