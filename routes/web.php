<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ForumController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The Blade pages use the session guard and CSRF protection, and they call the
| same services as the API: validation, authorisation and audit behaviour stay
| identical for both entry points.
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

// Forum -----------------------------------------------------------------
Route::get('/forum', [ForumController::class, 'index'])->name('forum.index');
// Writing requires a verified address; reading stays open to everyone. The
// "verified" gate is what makes the mail confirmation step meaningful.
Route::get('/forum/create', [ForumController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('forum.create');

Route::post('/forum', [ForumController::class, 'store'])
    ->middleware(['auth', 'verified', 'throttle:posts'])
    ->name('forum.store');

Route::get('/forum/{post}', [ForumController::class, 'show'])->name('forum.show');
Route::get('/forum/{post}/edit', [ForumController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('forum.edit');

Route::put('/forum/{post}', [ForumController::class, 'update'])
    ->middleware(['auth', 'verified', 'throttle:posts'])
    ->name('forum.update');

// Deleting stays available without the gate: a moderator must be able to
// remove content even when their own mailbox is in a broken state.
Route::delete('/forum/{post}', [ForumController::class, 'destroy'])->middleware('auth')->name('forum.destroy');

Route::post('/forum/{post}/comments', [ForumController::class, 'comment'])
    ->middleware(['auth', 'verified', 'throttle:posts'])
    ->name('forum.comments.store');

Route::post('/forum/{post}/like', [ForumController::class, 'like'])
    ->middleware(['auth', 'verified', 'throttle:posts'])
    ->name('forum.like');
Route::delete('/forum/{post}/like', [ForumController::class, 'unlike'])
    ->middleware(['auth', 'verified', 'throttle:posts'])
    ->name('forum.unlike');

Route::post('/forum/{post}/pin', [ForumController::class, 'pin'])->middleware('auth')->name('forum.pin');

// Authentication --------------------------------------------------------
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Account --------------------------------------------------------------
Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])
        ->middleware('throttle:uploads')
        ->name('profile.avatar');

    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::delete('/sessions', [SessionController::class, 'revokeOthers'])->name('sessions.revokeOthers');
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
});

// The link inside the verification mail points here: a browser must land on a
// page, not on a raw JSON envelope. API clients keep their own endpoint under
// the name "api.auth.verification.verify".
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:email_verification'])
    ->name('verification.verify');

// The resend action used by the pages.
Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
    ->middleware(['auth', 'throttle:email_verification'])
    ->name('verification.send');
