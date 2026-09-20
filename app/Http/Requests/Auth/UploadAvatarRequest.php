<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    /**
     * Max avatar size in kilobytes (2 MB).
     */
    public const MAX_KILOBYTES = 2048;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // VULN: no type, content or size validation at all.
            'avatar' => [
                'required',
                'file',
            ],
        ];
    }
}
