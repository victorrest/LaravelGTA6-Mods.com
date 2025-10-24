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
        // Make subject_type and subject_id nullable for status updates
        Schema::table('user_activities', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->change();
            $table->unsignedBigInteger('subject_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            $table->string('subject_type')->nullable(false)->change();
            $table->unsignedBigInteger('subject_id')->nullable(false)->change();
        });
    }
};
