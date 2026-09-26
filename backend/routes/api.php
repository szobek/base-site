<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:auth');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:auth');

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('password', [ProfileController::class, 'updatePassword']);
        Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('media/limits', [MediaController::class, 'limits']);
    Route::get('media', [MediaController::class, 'index']);
    Route::post('media', [MediaController::class, 'store'])->middleware('throttle:uploads');
    Route::get('media/{media}', [MediaController::class, 'show']);
    Route::patch('media/{media}', [MediaController::class, 'update']);
    Route::delete('media/{media}', [MediaController::class, 'destroy']);
});

Route::get('settings', [SettingsController::class, 'show']);
Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:contact');

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('users', [AdminUserController::class, 'index']);
    Route::patch('users/{user}', [AdminUserController::class, 'update']);
    Route::put('settings', [SettingsController::class, 'update']);
    Route::get('messages', [ContactMessageController::class, 'index']);
    Route::patch('messages/{message}', [ContactMessageController::class, 'read']);
    Route::delete('messages/{message}', [ContactMessageController::class, 'destroy']);
});

Route::middleware('sanctum.optional')->group(function () {
    Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('posts/{post}', [PostController::class, 'show'])->name('posts.show');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
});
