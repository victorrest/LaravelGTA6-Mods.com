<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->index(['status', 'published_at']);
            $table->index(['status', 'downloads']);
            $table->index(['status', 'likes']);
            $table->index(['featured', 'published_at']);
        });

        Schema::table('mod_mod_category', function (Blueprint $table) {
            $table->index('mod_category_id');
        });

        Schema::table('mod_comments', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('mod_ratings', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('mod_download_tokens', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('used_at');
        });

        Schema::table('forum_threads', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('pinned');
            $table->index('locked');
            $table->index('last_posted_at');
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->index('is_approved');
            $table->index('created_at');
        });

        Schema::table('news_articles', function (Blueprint $table) {
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('mods', function (Blueprint $table) {
            $table->dropIndex(['status', 'published_at']);
            $table->dropIndex(['status', 'downloads']);
            $table->dropIndex(['status', 'likes']);
            $table->dropIndex(['featured', 'published_at']);
        });

        Schema::table('mod_mod_category', function (Blueprint $table) {
            $table->dropIndex(['mod_category_id']);
        });

        Schema::table('mod_comments', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('mod_ratings', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('mod_download_tokens', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['used_at']);
        });

        Schema::table('forum_threads', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['pinned']);
            $table->dropIndex(['locked']);
            $table->dropIndex(['last_posted_at']);
        });

        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropIndex(['is_approved']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('news_articles', function (Blueprint $table) {
            $table->dropIndex(['published_at']);
        });
    }
};
