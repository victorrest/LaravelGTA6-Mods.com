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
        Schema::create('mod_video_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained('mod_videos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('reported_at')->useCurrent();

            // Prevent duplicate reports from the same user
            $table->unique(['video_id', 'user_id']);
            $table->index('video_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_video_reports');
    }
};
