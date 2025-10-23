<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->json('authors')->nullable()->after('user_id');
            $table->json('tag_list')->nullable()->after('download_url');
            $table->string('video_permission')->default('self_moderate')->after('tag_list');
        });
    }

    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->dropColumn(['authors', 'tag_list', 'video_permission']);
        });
    }
};
