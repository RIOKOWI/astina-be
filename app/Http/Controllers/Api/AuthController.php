<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login(
            $request->validated('phone'),
            $request->validated('password'),
        );

        $token = $user->createToken('astina-mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
            'meta' => null,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'resident']);

        return response()->json([
            'success' => true,
            'message' => 'Data pengguna berhasil diambil.',
            'data' => new UserResource($user),
            'meta' => null,
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->authService->logout($request->user());

        return response()->noContent();
    }
}
