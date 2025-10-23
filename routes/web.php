<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CommentController as AdminCommentController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ForumController as AdminForumController;
use App\Http\Controllers\Admin\ModController as AdminModController;
use App\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ModController;
use App\Http\Controllers\ModDownloadController;
use App\Http\Controllers\ModManagementController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\AuthorProfileController;
use App\Http\Controllers\ProfileSettingsController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\FollowController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/install', [InstallController::class, 'index'])->name('install.index');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/mods', [ModController::class, 'index'])->name('mods.index');
Route::middleware('auth')->group(function () {
    Route::get('/mods/upload', [ModManagementController::class, 'create'])->name('mods.upload');
    Route::post('/mods', [ModManagementController::class, 'store'])->name('mods.store');
    Route::get('/dashboard/mods', [ModManagementController::class, 'myMods'])->name('mods.my');
});
Route::get('/download/{downloadToken:token}', [ModDownloadController::class, 'show'])->name('mods.download.waiting');
Route::post('/download/{downloadToken:token}/complete', [ModDownloadController::class, 'complete'])->name('mods.download.complete');

Route::get('/forum', [ForumController::class, 'index'])->name('forum.index');
Route::get('/forum/create', [ForumController::class, 'create'])->middleware('auth')->name('forum.create');
Route::post('/forum', [ForumController::class, 'store'])->middleware('auth')->name('forum.store');
Route::get('/forum/{thread:slug}', [ForumController::class, 'show'])->name('forum.show');
Route::post('/forum/{thread:slug}/reply', [ForumController::class, 'reply'])->middleware('auth')->name('forum.reply');

Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{article:slug}', [NewsController::class, 'show'])->name('news.show');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('mods', [AdminModController::class, 'index'])->name('mods.index');
    Route::get('mods/{mod}/edit', [AdminModController::class, 'edit'])->name('mods.edit');
    Route::put('mods/{mod}', [AdminModController::class, 'update'])->name('mods.update');
    Route::delete('mods/{mod}', [AdminModController::class, 'destroy'])->name('mods.destroy');

    Route::get('categories', [AdminCategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [AdminCategoryController::class, 'store'])->name('categories.store');
    Route::put('categories/{category}', [AdminCategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('news', [AdminNewsController::class, 'index'])->name('news.index');
    Route::get('news/create', [AdminNewsController::class, 'create'])->name('news.create');
    Route::post('news', [AdminNewsController::class, 'store'])->name('news.store');
    Route::get('news/{news}/edit', [AdminNewsController::class, 'edit'])->name('news.edit');
    Route::put('news/{news}', [AdminNewsController::class, 'update'])->name('news.update');
    Route::delete('news/{news}', [AdminNewsController::class, 'destroy'])->name('news.destroy');

    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');

    Route::get('forum', [AdminForumController::class, 'index'])->name('forum.index');
    Route::get('forum/{forumThread}/edit', [AdminForumController::class, 'edit'])->name('forum.edit');
    Route::put('forum/{forumThread}', [AdminForumController::class, 'update'])->name('forum.update');
    Route::delete('forum/{forumThread}', [AdminForumController::class, 'destroy'])->name('forum.destroy');
    Route::delete('forum/{forumThread}/posts/{post}', [AdminForumController::class, 'destroyPost'])->name('forum.posts.destroy');

    Route::get('comments', [AdminCommentController::class, 'index'])->name('comments.index');
    Route::delete('comments/{comment}', [AdminCommentController::class, 'destroy'])->name('comments.destroy');
});

// Author Profile Routes
Route::get('/author/{username}', [AuthorProfileController::class, 'show'])->name('author.profile');

// Profile Settings Routes (Auth Required)
Route::middleware('auth')->prefix('profile')->name('profile.')->group(function () {
    Route::post('/settings', [ProfileSettingsController::class, 'updateProfile'])->name('settings.update');
    Route::post('/avatar', [ProfileSettingsController::class, 'uploadAvatar'])->name('avatar.upload');
    Route::post('/avatar/preset', [ProfileSettingsController::class, 'selectPresetAvatar'])->name('avatar.preset');
    Route::delete('/avatar', [ProfileSettingsController::class, 'deleteAvatar'])->name('avatar.delete');
    Route::post('/banner', [ProfileSettingsController::class, 'uploadBanner'])->name('banner.upload');
    Route::delete('/banner', [ProfileSettingsController::class, 'deleteBanner'])->name('banner.delete');
    Route::post('/social-links', [ProfileSettingsController::class, 'updateSocialLinks'])->name('social.update');
    Route::post('/password', [ProfileSettingsController::class, 'changePassword'])->name('password.change');
    Route::post('/pin-mod/{mod}', [ProfileSettingsController::class, 'pinMod'])->name('mod.pin');
    Route::delete('/pin-mod', [ProfileSettingsController::class, 'unpinMod'])->name('mod.unpin');
});

// Activity Routes
Route::middleware('auth')->prefix('activity')->name('activity.')->group(function () {
    Route::post('/status', [ActivityController::class, 'createStatus'])->name('status.create');
    Route::delete('/status/{id}', [ActivityController::class, 'deleteStatus'])->name('status.delete');
});

// API Routes for async content loading
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/author/{userId}/activities', [ActivityController::class, 'getUserActivities'])->name('author.activities');
    Route::get('/author/{userId}/followers', [FollowController::class, 'followers'])->name('author.followers');
    Route::get('/author/{userId}/following', [FollowController::class, 'following'])->name('author.following');

    Route::middleware('auth')->group(function () {
        // Bookmarks
        Route::get('/bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
        Route::post('/bookmarks/{modId}/toggle', [BookmarkController::class, 'toggle'])->name('bookmarks.toggle');
        Route::get('/bookmarks/{modId}/check', [BookmarkController::class, 'check'])->name('bookmarks.check');

        // Follow
        Route::post('/follow/{userId}/toggle', [FollowController::class, 'toggle'])->name('follow.toggle');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    });
});

// Legacy redirects for old mod URLs
Route::get('/mods/{mod:slug}', function (\App\Models\Mod $mod) {
    if ($mod->primary_category) {
        return redirect()->route('mods.show', [$mod->primary_category, $mod], 301);
    }
    return redirect()->route('mods.index');
})->name('mods.show.legacy');

Route::post('/mods/{mod:slug}/download', function (\App\Models\Mod $mod) {
    if ($mod->primary_category) {
        return redirect()->route('mods.download', [$mod->primary_category, $mod], 307);
    }
    return redirect()->route('mods.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/mods/{mod:slug}/edit', function (\App\Models\Mod $mod) {
        if ($mod->primary_category) {
            return redirect()->route('mods.edit', [$mod->primary_category, $mod], 301);
        }
        return redirect()->route('mods.index');
    });

    Route::post('/mods/{mod:slug}/rate', function (\App\Models\Mod $mod) {
        if ($mod->primary_category) {
            return redirect()->route('mods.rate', [$mod->primary_category, $mod], 307);
        }
        return redirect()->route('mods.index');
    });

    Route::post('/mods/{mod:slug}/comment', function (\App\Models\Mod $mod) {
        if ($mod->primary_category) {
            return redirect()->route('mods.comment', [$mod->primary_category, $mod], 307);
        }
        return redirect()->route('mods.index');
    });
});

// Category-based mod routes (must be at the end to avoid conflicts)
Route::get('/{category:slug}/{mod:slug}', [ModController::class, 'show'])->name('mods.show');
Route::middleware('auth')->group(function () {
    Route::get('/{category:slug}/{mod:slug}/edit', [ModManagementController::class, 'edit'])->name('mods.edit');
    Route::put('/{category:slug}/{mod:slug}', [ModManagementController::class, 'update'])->name('mods.update');
    Route::post('/{category:slug}/{mod:slug}/rate', [ModController::class, 'rate'])->name('mods.rate');
    Route::post('/{category:slug}/{mod:slug}/comment', [ModController::class, 'comment'])->name('mods.comment');
});
Route::post('/{category:slug}/{mod:slug}/download', [ModDownloadController::class, 'store'])->name('mods.download');
