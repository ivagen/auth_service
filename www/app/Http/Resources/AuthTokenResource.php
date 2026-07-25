<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\AuthTokenPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes the result of an authentication (register/login) into a stable
 * token envelope.
 *
 * @property AuthTokenPayload $resource
 */
class AuthTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->resource->expiresAt,
            'user' => new UserResource($this->resource->user),
        ];
    }
}
