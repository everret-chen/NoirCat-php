<?php

declare(strict_types=1);

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ForumController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\ModerationController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ReportController;
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
// VULN: writing no longer requires a verified address.
Route::get('/forum/create', [ForumController::class, 'create'])
    // VULN: no verified gate.
    ->middleware('auth')
    ->name('forum.create');

Route::post('/forum', [ForumController::class, 'store'])
    // VULN: no verified gate - an unverified account can write.
    ->middleware(['auth', 'throttle:posts'])
    ->name('forum.store');

Route::get('/forum/{post}', [ForumController::class, 'show'])->name('forum.show');
Route::get('/forum/{post}/edit', [ForumController::class, 'edit'])
    // VULN: no verified gate.
    ->middleware('auth')
    ->name('forum.edit');

Route::put('/forum/{post}', [ForumController::class, 'update'])
    // VULN: no verified gate - an unverified account can write.
    ->middleware(['auth', 'throttle:posts'])
    ->name('forum.update');

// Deleting stays available without the gate: a moderator must be able to
// remove content even when their own mailbox is in a broken state.
Route::delete('/forum/{post}', [ForumController::class, 'destroy'])->middleware('auth')->name('forum.destroy');

Route::post('/forum/{post}/comments', [ForumController::class, 'comment'])
    // VULN: no verified gate - an unverified account can write.
    ->middleware(['auth', 'throttle:posts'])
    ->name('forum.comments.store');

Route::post('/forum/{post}/like', [ForumController::class, 'like'])
    // VULN: no verified gate - an unverified account can write.
    ->middleware(['auth', 'throttle:posts'])
    ->name('forum.like');
Route::delete('/forum/{post}/like', [ForumController::class, 'unlike'])
    // VULN: no verified gate - an unverified account can write.
    ->middleware(['auth', 'throttle:posts'])
    ->name('forum.unlike');

// Reporting is a member action: an account and a verified address are enough.
Route::post('/forum/{post}/report', [ReportController::class, 'storeForPost'])
    ->middleware(['auth', 'verified', 'throttle:posts'])
    ->name('forum.report');
Route::post('/forum/{post}/comments/{comment}/report', [ReportController::class, 'storeForComment'])
    ->middleware(['auth', 'verified', 'throttle:posts'])
    ->name('forum.comments.report');

/*
|--------------------------------------------------------------------------
| Moderation
|--------------------------------------------------------------------------
|
| The governance pages and the actions a moderator performs on a thread. Every
| action authorises through a policy; the routes only require an account, so an
| ordinary member gets a clean 403 instead of a permission middleware error.
|
*/

Route::middleware('auth')->prefix('moderation')->name('moderation.')->group(function (): void {
    Route::get('/reports', [ModerationController::class, 'reports'])->name('reports');
    Route::post('/reports/{report}/resolve', [ModerationController::class, 'resolveReport'])->name('reports.resolve');
    Route::post('/reports/{report}/dismiss', [ModerationController::class, 'dismissReport'])->name('reports.dismiss');

    Route::get('/trash', [ModerationController::class, 'trash'])->name('trash');
    // A soft deleted row is not reachable through route model binding, so these
    // two take a plain id and look it up with onlyTrashed().
    Route::post('/trash/posts/{post}/restore', [ModerationController::class, 'restorePost'])
        ->whereNumber('post')
        ->name('trash.posts.restore');
    Route::post('/trash/comments/{comment}/restore', [ModerationController::class, 'restoreComment'])
        ->whereNumber('comment')
        ->name('trash.comments.restore');

    Route::post('/forum/{post}/pin', [ModerationController::class, 'pin'])->name('forum.pin');
    Route::post('/forum/{post}/feature', [ModerationController::class, 'feature'])->name('forum.feature');
    Route::post('/forum/{post}/lock', [ModerationController::class, 'lock'])->name('forum.lock');
    Route::put('/forum/{post}/category', [ModerationController::class, 'move'])->name('forum.move');

    Route::post('/comments/{comment}/hide', [ModerationController::class, 'hideComment'])->name('comments.hide');
    Route::delete('/comments/{comment}/hide', [ModerationController::class, 'unhideComment'])->name('comments.unhide');
    Route::delete('/comments/{comment}', [ModerationController::class, 'deleteComment'])->name('comments.destroy');
});

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
// VULN: the "signed" middleware is gone on this branch (same flaw as V9), so a
// link can be forged by hand instead of coming from the signed mail URL.
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['throttle:email_verification'])
    ->name('verification.verify');

// The resend action used by the pages.
Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
    ->middleware(['auth', 'throttle:email_verification'])
    ->name('verification.send');
