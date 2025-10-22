<?php

namespace Database\Seeders;

use App\Models\ForumPost;
use App\Models\ForumThread;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModComment;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'GTA Nexus Admin',
            'email' => 'admin@gta6-mods.com',
        ]);

        $creators = User::factory(9)->create();
        $users = $creators->prepend($admin);

        $categories = collect(config('gta6.navigation'))
            ->map(fn (array $item) => ModCategory::factory()->create([
                'name' => $item['label'],
                'slug' => $item['slug'],
                'icon' => 'fa-solid ' . ($item['icon'] ?? 'fa-star'),
            ]));

        $mods = Mod::factory(24)
            ->recycle($users)
            ->create();

        $mods->each(function (Mod $mod) use ($categories, $users) {
            $mod->categories()->sync($categories->random(rand(1, 3))->pluck('id'));

            ModComment::factory(rand(2, 6))
                ->for($mod)
                ->recycle($users)
                ->create();
        });

        $threads = ForumThread::factory(10)
            ->recycle($users)
            ->create();

        $threads->each(function (ForumThread $thread) use ($users) {
            ForumPost::factory(rand(3, 10))
                ->for($thread)
                ->recycle($users)
                ->create();

            $thread->update([
                'replies_count' => $thread->posts()->count(),
                'last_posted_at' => $thread->posts()->latest('created_at')->value('created_at'),
            ]);
        });

        NewsArticle::factory(6)
            ->recycle($users)
            ->create();
    }
}
