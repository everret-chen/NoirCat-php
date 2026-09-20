<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UploadAvatarRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

class ProfileController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        $this->authService->updateProfile($user, $request->validated());

        return back()->with('status', __('profile_ui.updated'));
    }

    public function uploadAvatar(UploadAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        /** @var UploadedFile $file */
        $file = $request->file('avatar');

        $this->authService->updateAvatar($user, $file);

        return back()->with('status', __('api.messages.avatar_updated'));
    }
}
