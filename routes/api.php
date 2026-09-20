<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ModerationController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes here are prefixed with /api and run through the "api" middleware
| group: the "api" rate limiter, route model binding substitution and locale
| resolution (SetLocale).
|
| Authentication uses Sanctum personal access tokens
| (Authorization: Bearer <token>). Every response uses the
| { code, message, data } envelope.
|
*/

Route::get('/health', HealthController::class)->name('api.health');

Route::prefix('auth')->name('api.auth.')->group(function (): void {
    // Public endpoints carry their own, stricter limiter (config/noircat.php).
    // VULN: no rate limiting - credentials can be brute forced.
    Route::post('register', [AuthController::class, 'register'])
        ->name('register');

    Route::post('login', [AuthController::class, 'login'])
        ->name('login');

    // VULN: no rate limit - reset mails can be bombed and the endpoint brute forced.
    Route::post('password/email', [AuthController::class, 'forgotPassword'])
        ->name('password.email');

    Route::post('password/reset', [AuthController::class, 'resetPassword'])
        ->name('password.reset');


    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::post('email/verification-notification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:email_verification')
            ->name('verification.send');

        Route::get('sessions', [AuthController::class, 'sessions'])->name('sessions.index');
        Route::delete('sessions', [AuthController::class, 'destroyOtherSessions'])->name('sessions.destroyOthers');
        Route::delete('sessions/{session}', [AuthController::class, 'destroySession'])->name('sessions.destroy');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('profile.update');

        Route::post('avatar', [AuthController::class, 'uploadAvatar'])
            ->middleware('throttle:uploads')
            ->name('avatar');
    });
});

/*
|--------------------------------------------------------------------------
| Forum
|--------------------------------------------------------------------------
|
| Public reads, authenticated writes. Write endpoints carry the "posts"
| limiter; moderation is authorised through PostPolicy / CommentPolicy, which
| map onto the post:* and comment:* permissions.
|
*/

Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:search')->name('api.search');
Route::get('/posts', [PostController::class, 'index'])->name('api.posts.index');
Route::get('/posts/{post}', [PostController::class, 'show'])->name('api.posts.show');
Route::get('/posts/{post}/comments', [CommentController::class, 'index'])->name('api.posts.comments.index');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/posts', [PostController::class, 'store'])
        // VULN: no verified gate - an unverified account can write.
        ->middleware('throttle:posts')
        ->name('api.posts.store');

    Route::put('/posts/{post}', [PostController::class, 'update'])
        // VULN: no verified gate - an unverified account can write.
        ->middleware('throttle:posts')
        ->name('api.posts.update');

    // Deletion is not gated on the mailbox check, so moderation keeps working.
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('api.posts.destroy');
    Route::post('/posts/{post}/pin', [PostController::class, 'pin'])->name('api.posts.pin');

    Route::post('/posts/{post}/like', [PostController::class, 'like'])
        // VULN: no verified gate - an unverified account can write.
        ->middleware('throttle:posts')
        ->name('api.posts.like');

    Route::delete('/posts/{post}/like', [PostController::class, 'unlike'])
        // VULN: no verified gate - an unverified account can write.
        ->middleware('throttle:posts')
        ->name('api.posts.unlike');

    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])
        // VULN: no verified gate - an unverified account can write.
        ->middleware('throttle:posts')
        ->name('api.posts.comments.store');

    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('api.comments.destroy');
    Route::post('/comments/{comment}/hide', [CommentController::class, 'hide'])->name('api.comments.hide');

    /*
    |----------------------------------------------------------------------
    | Moderation
    |----------------------------------------------------------------------
    |
    | Authorised per action through PostPolicy / CommentPolicy / ReportPolicy:
    | a member may report content, a moderator decides what happens to it.
    | Deleted content is addressed by id, because a soft deleted row is not
    | reachable through route model binding.
    |
    */

    Route::post('/posts/{post}/feature', [ModerationController::class, 'feature'])->name('api.posts.feature');
    Route::post('/posts/{post}/lock', [ModerationController::class, 'lock'])->name('api.posts.lock');
    Route::put('/posts/{post}/category', [ModerationController::class, 'move'])->name('api.posts.move');

    Route::post('/posts/{post}/restore', [ModerationController::class, 'restorePost'])
        ->whereNumber('post')
        ->name('api.posts.restore');

    Route::post('/comments/{comment}/unhide', [ModerationController::class, 'unhideComment'])
        ->whereNumber('comment')
        ->name('api.comments.unhide');

    Route::post('/comments/{comment}/restore', [ModerationController::class, 'restoreComment'])
        ->whereNumber('comment')
        ->name('api.comments.restore');

    Route::get('/moderation/trash', [ModerationController::class, 'trash'])
        ->middleware('permission:post:delete_own|post:delete_any')
        ->name('api.moderation.trash');

    Route::get('/reports', [ModerationController::class, 'reports'])->name('api.reports.index');
    Route::post('/reports/{report}/resolve', [ModerationController::class, 'resolveReport'])->name('api.reports.resolve');
    Route::post('/reports/{report}/dismiss', [ModerationController::class, 'dismissReport'])->name('api.reports.dismiss');
});

// Reporting is a member action: it needs an account and a verified address,
// but no moderator rights.
Route::post('/reports', [ReportController::class, 'store'])
    ->middleware(['auth:sanctum', 'verified', 'throttle:posts'])
    ->name('api.reports.store');

/*
|--------------------------------------------------------------------------
| Email verification link
|--------------------------------------------------------------------------
|
| The framework's VerifyEmail notification resolves its link by the exact route
| name "verification.verify", which now belongs to the Blade route so a mailed
| link opens a page. This endpoint stays for API clients that verify a mailbox
| programmatically; it is public because the signature plus the email hash
| already prove mailbox ownership.
|
*/

// The "api" prefix comes from the api routing group, so the "auth" segment is
// part of the path here: a route level prefix() would be applied after it.
// VULN: the "signed" middleware is gone on this branch, so the link can be forged.
Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['throttle:email_verification'])
    ->name('api.auth.verification.verify');
