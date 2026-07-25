<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

class LoginRequest extends AuthRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:72'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
