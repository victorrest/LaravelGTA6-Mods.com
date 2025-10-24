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
        Schema::table('mod_comments', function (Blueprint $table) {
            if (! Schema::hasColumn('mod_comments', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('mod_comments')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('mod_comments', 'likes_count')) {
                $table->unsignedSmallInteger('likes_count')->default(0)->after('body');
            }

            if (! Schema::hasColumn('mod_comments', 'status')) {
                $table->string('status', 32)->default('approved')->after('likes_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mod_comments', function (Blueprint $table) {
            if (Schema::hasColumn('mod_comments', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('mod_comments', 'likes_count')) {
                $table->dropColumn('likes_count');
            }

            if (Schema::hasColumn('mod_comments', 'parent_id')) {
                try {
                    $table->dropForeign(['parent_id']);
                } catch (\Throwable $e) {
                    // The foreign key might already be missing; ignore failures so the column can be dropped.
                }

                $table->dropColumn('parent_id');
            }
        });
    }
};
