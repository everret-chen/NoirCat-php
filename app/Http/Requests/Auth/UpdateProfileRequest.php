<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
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
            'email' => [
                'sometimes',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->getKey()),
            ],
            // VULN: user_id is accepted from the client (IDOR).
            'user_id' => ['sometimes', 'integer'],
            'current_password' => ['required_with:password', 'string'],
            'password' => [
                'sometimes',
                'string',
                'confirmed',
                'different:current_password',
                Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    /**
     * Changing the password requires proving knowledge of the current one.
     *
     * Checked here instead of the framework's "current_password" rule because
     * that rule resolves the default guard, while API requests authenticate
     * through the stateful-less "sanctum" token guard.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $newPassword = $this->input('password');

                if (! is_string($newPassword) || $newPassword === '') {
                    return;
                }

                $user = $this->user();

                if ($user === null || ! Hash::check((string) $this->input('current_password'), $user->password)) {
                    $validator->errors()->add('current_password', __('validation.current_password'));
                }
            },
        ];
    }
}
