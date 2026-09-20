<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuthService;
use App\Services\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly SessionService $sessionService,
    ) {
    }

    /**
     * POST /api/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());
        $token = $this->authService->issueToken($user, $this->deviceName($request));

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ], __('api.messages.registered'), 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var string $account */
        $account = $data['account'];
        /** @var string $password */
        $password = $data['password'];
        /** @var string|null $deviceName */
        $deviceName = $data['device_name'] ?? null;

        $user = $this->authService->authenticate($account, $password);
        $token = $this->authService->issueToken($user, $deviceName ?? $this->deviceName($request));

        return ApiResponse::success([
            'token' => $token,
            'user' => new UserResource($user),
        ], __('api.messages.logged_in'));
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($this->currentUser($request)));
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($this->currentUser($request));

        return ApiResponse::success(null, __('api.messages.logged_out'));
    }

    /**
     * PUT /api/auth/profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        // VULN: any authenticated user can update somebody else's account.
        $target = User::query()->find($request->input('user_id')) ?? $this->currentUser($request);

        $updated = $this->authService->updateProfile($target, $request->validated());

        return ApiResponse::success(new UserResource($updated), __('api.messages.profile_updated'));
    }

    /**
     * POST /api/auth/avatar
     */
    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('avatar');

        $updated = $this->authService->updateAvatar($this->currentUser($request), $file);

        return ApiResponse::success(new UserResource($updated), __('api.messages.avatar_updated'));
    }

    /**
     * POST /api/auth/password/email
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        /** @var string $email */
        $email = $request->validated()['email'];

        $this->authService->sendPasswordResetLink($email);

        return ApiResponse::success(null, __('api.messages.password_reset_link_sent'));
    }

    /**
     * POST /api/auth/password/reset
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->validated());

        return ApiResponse::success(null, __('api.messages.password_reset'));
    }

    /**
     * GET /api/auth/email/verify/{id}/{hash}
     */
    public function verifyEmail(Request $request, int|string $id, string $hash): JsonResponse
    {
        $user = $this->authService->verifyEmail($id, $hash);

        return ApiResponse::success(new UserResource($user), __('api.messages.email_verified'));
    }

    /**
     * POST /api/auth/email/verification-notification
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(new UserResource($user), __('api.messages.already_verified'));
        }

        $this->authService->sendEmailVerification($user);

        return ApiResponse::success(new UserResource($user), __('api.messages.verification_sent'));
    }

    /**
     * GET /api/auth/sessions
     */
    public function sessions(Request $request): JsonResponse
    {
        return ApiResponse::success($this->sessionService->listForApi($this->currentUser($request)));
    }

    /**
     * DELETE /api/auth/sessions/{session}
     */
    public function destroySession(Request $request, int|string $session): JsonResponse
    {
        $this->sessionService->revoke($this->currentUser($request), $session);

        return ApiResponse::success(null, __('api.messages.session_revoked'));
    }

    /**
     * DELETE /api/auth/sessions
     */
    public function destroyOtherSessions(Request $request): JsonResponse
    {
        $revoked = $this->sessionService->revokeOthers($this->currentUser($request));

        return ApiResponse::success(['revoked' => $revoked], __('api.messages.sessions_revoked'));
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function deviceName(Request $request): string
    {
        $agent = $request->userAgent();

        return Str::limit($agent === null || $agent === '' ? 'api' : $agent, 64, '');
    }
}
