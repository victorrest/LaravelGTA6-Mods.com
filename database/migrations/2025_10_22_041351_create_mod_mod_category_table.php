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
        Schema::create('mod_mod_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mod_id')->constrained('mods')->cascadeOnDelete();
            $table->foreignId('mod_category_id')->constrained('mod_categories')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['mod_id', 'mod_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mod_mod_category');
    }
};
