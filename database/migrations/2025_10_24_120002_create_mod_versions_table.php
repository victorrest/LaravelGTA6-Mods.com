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
        Schema::create('mod_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_id')->constrained('mods')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('version_number');
            $table->text('changelog')->nullable();
            $table->string('file_path')->nullable();
            $table->string('download_url')->nullable();
            $table->decimal('file_size', 10, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_current')->default(false); // Indicates if this is the active version
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indices
            $table->index('mod_id');
            $table->index('status');
            $table->index(['mod_id', 'is_current']);
            $table->unique(['mod_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_versions');
    }
};
