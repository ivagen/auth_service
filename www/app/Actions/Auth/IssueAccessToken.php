<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Passport\PersonalAccessTokenResult;

class IssueAccessToken
{
    /**
     * Issue a personal access token for the user. The token name identifies
     * the device/client so tokens can be told apart and revoked selectively.
     */
    public function handle(User $user, string $name = 'api'): PersonalAccessTokenResult
    {
        return $user->createToken($name);
    }
}
