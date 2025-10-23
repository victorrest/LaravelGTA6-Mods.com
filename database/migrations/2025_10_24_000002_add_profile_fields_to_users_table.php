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
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_title')->nullable()->after('name');
            $table->text('bio')->nullable()->after('profile_title');
            $table->string('avatar')->nullable()->after('bio');
            $table->string('avatar_type')->default('default')->after('avatar'); // default, preset, custom
            $table->string('avatar_preset_id')->nullable()->after('avatar_type');
            $table->string('banner')->nullable()->after('avatar_preset_id');
            $table->integer('profile_views')->default(0)->after('banner');
            $table->timestamp('last_activity_at')->nullable()->after('profile_views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_title',
                'bio',
                'avatar',
                'avatar_type',
                'avatar_preset_id',
                'banner',
                'profile_views',
                'last_activity_at',
            ]);
        });
    }
};
