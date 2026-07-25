<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateUser
{
    /**
     * Verify credentials without opening a server-side session. This is a
     * stateless API: we look the user up and check the hash directly instead
     * of Auth::attempt(), which would drive the session-based web guard.
     *
     * @throws ValidationException when the credentials do not match
     */
    public function handle(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return $user;
    }
}
