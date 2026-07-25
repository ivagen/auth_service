<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;

class RegisterUser
{
    /**
     * Persist a new user. The password is hashed by the model's cast.
     *
     * @param array{name: string, email: string, password: string} $data
     */
    public function handle(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
    }
}
