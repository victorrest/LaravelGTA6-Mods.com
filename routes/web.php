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
use App\Http\Controllers\ChunkedUploadController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ModController;
use App\Http\Controllers\ModManagementController;
use App\Http\Controllers\NewsController;
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
    Route::post('/uploads/chunks', [ChunkedUploadController::class, 'store'])->name('uploads.chunks');
    Route::get('/mods/upload', [ModManagementController::class, 'create'])->name('mods.upload');
    Route::post('/mods', [ModManagementController::class, 'store'])->name('mods.store');
    Route::get('/mods/{mod:slug}/edit', [ModManagementController::class, 'edit'])->name('mods.edit');
    Route::put('/mods/{mod:slug}', [ModManagementController::class, 'update'])->name('mods.update');
    Route::get('/dashboard/mods', [ModManagementController::class, 'myMods'])->name('mods.my');
});
Route::get('/mods/{mod:slug}', [ModController::class, 'show'])->name('mods.show');
Route::post('/mods/{mod:slug}/comment', [ModController::class, 'comment'])->middleware('auth')->name('mods.comment');
Route::post('/mods/{mod:slug}/download', [ModController::class, 'download'])->name('mods.download');

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
