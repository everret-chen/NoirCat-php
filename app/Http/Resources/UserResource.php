<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'avatar_url' => $this->avatar === null
                ? null
                : Storage::disk(AuthService::AVATAR_DISK)->url($this->avatar),
            'role' => $this->role->value,
            'roles' => $this->getRoleNames()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->all(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
