<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Public author card: never exposes email, roles or permissions.
 *
 * @mixin \App\Models\User
 */
class AuthorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'avatar_url' => $this->avatar === null
                ? null
                : Storage::disk(AuthService::AVATAR_DISK)->url($this->avatar),
        ];
    }
}
