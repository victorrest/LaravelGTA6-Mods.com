<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForumController;
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
