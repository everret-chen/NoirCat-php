<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UploadAvatarRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
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
        $updated = $this->authService->updateProfile($this->currentUser($request), $request->validated());

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
