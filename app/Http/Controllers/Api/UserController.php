<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\CreateResidentAccountRequest;
use App\Http\Requests\User\ResetUserPasswordRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UserIndexRequest;
use App\Http\Resources\User\UserAdminResource;
use App\Http\Resources\User\UserResource;
use App\Models\Resident;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function index(UserIndexRequest $request): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses.');
        }

        $params = $request->validated();
        $users = $this->userService->getList(
            $params['search'] ?? null,
            $params['is_active'] ?? null,
            $params['role'] ?? null,
            $params['per_page'] ?? 15,
        );

        return response()->json([
            'success' => true,
            'message' => 'Daftar user berhasil diambil.',
            'data' => UserResource::collection($users),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses.');
        }

        $user = $this->userService->getDetail($user);

        return response()->json([
            'success' => true,
            'message' => 'Detail user berhasil diambil.',
            'data' => new UserAdminResource($user),
            'meta' => null,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses.');
        }

        $user = $this->userService->update($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diperbarui.',
            'data' => new UserAdminResource($user),
            'meta' => null,
        ]);
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses.');
        }

        $this->userService->resetPassword($user, Hash::make($request->password));

        return response()->json([
            'success' => true,
            'message' => 'Password pengguna berhasil direset.',
            'data' => null,
            'meta' => null,
        ]);
    }

    public function storeForResident(CreateResidentAccountRequest $request, Resident $resident): JsonResponse
    {
        $user = $this->userService->createResidentAccount($resident, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Akun warga berhasil dibuat.',
            'data' => new UserAdminResource($user),
            'meta' => null,
        ], 201);
    }
}
