<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\AuthenticateUser;
use App\Actions\Auth\IssueAccessToken;
use App\Actions\Auth\RegisterUser;
use App\Actions\Auth\RevokeAccessToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Support\AuthTokenPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request,
        RegisterUser $registerUser,
        IssueAccessToken $issueToken,
    ): JsonResponse {
        $payload = DB::transaction(function () use ($request, $registerUser, $issueToken): AuthTokenPayload {
            $user = $registerUser->handle($request->validated());

            return AuthTokenPayload::fromResult($user, $issueToken->handle($user, $request->deviceName()));
        });

        return $this->tokenResponse($payload, Response::HTTP_CREATED);
    }

    public function login(
        LoginRequest $request,
        AuthenticateUser $authenticate,
        IssueAccessToken $issueToken,
    ): JsonResponse {
        $data = $request->validated();

        $user = $authenticate->handle($data['email'], $data['password']);
        $token = $issueToken->handle($user, $request->deviceName());

        return $this->tokenResponse(AuthTokenPayload::fromResult($user, $token));
    }

    public function user(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    public function logout(Request $request, RevokeAccessToken $revoke): JsonResponse
    {
        $revoke->handle($request->user());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    private function tokenResponse(AuthTokenPayload $payload, int $status = Response::HTTP_OK): JsonResponse
    {
        return AuthTokenResource::make($payload)->response()->setStatusCode($status);
    }
}
