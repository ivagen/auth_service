<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Passport\AccessToken;

class RevokeAccessToken
{
    /**
     * Revoke the access token the current request authenticated with. Bearer
     * authentication attaches an AccessToken; anything else (e.g. a transient
     * cookie token) has nothing to revoke here.
     */
    public function handle(User $user): void
    {
        $token = $user->token();

        if ($token instanceof AccessToken) {
            $token->revoke();
        }
    }
}
