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
        Schema::create('mod_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_id')->constrained('mods')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('youtube_id');
            $table->string('youtube_url')->nullable();
            $table->string('video_title')->nullable();
            $table->text('video_description')->nullable();
            $table->string('duration')->nullable(); // ISO 8601 duration format (e.g., PT4M13S)
            $table->string('thumbnail_path')->nullable();
            $table->unsignedBigInteger('thumbnail_attachment_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'reported'])->default('pending');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('featured_at')->nullable();
            $table->unsignedInteger('report_count')->default(0);
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            // Indices
            $table->index('mod_id');
            $table->index('submitted_by');
            $table->index('status');
            $table->index(['mod_id', 'is_featured']);
            $table->unique(['mod_id', 'youtube_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_videos');
    }
};
