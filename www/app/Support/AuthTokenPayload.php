<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use DateTimeInterface;
use Laravel\Passport\PersonalAccessTokenResult;

/**
 * The result of a successful authentication: the user plus the freshly
 * issued personal access token, ready for serialization.
 */
final readonly class AuthTokenPayload
{
    public function __construct(
        public User $user,
        public string $accessToken,
        public ?DateTimeInterface $expiresAt,
    ) {
    }

    public static function fromResult(User $user, PersonalAccessTokenResult $token): self
    {
        return new self($user, $token->accessToken, $token->getToken()?->expires_at);
    }
}
