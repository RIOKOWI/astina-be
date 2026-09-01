<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceToken\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $user = $request->user();

        $token = DeviceToken::updateOrCreate(
            ['token' => $request->validated('token')],
            [
                'user_id' => $user->id,
                'platform' => $request->validated('platform'),
                'device_name' => $request->validated('device_name'),
                'last_used_at' => now(),
                'revoked_at' => null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Device token berhasil disimpan.',
            'data' => $token,
        ], 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $user = $request->user();

        $deviceToken = DeviceToken::where('token', $token)->first();

        if (! $deviceToken) {
            return response()->json([
                'success' => false,
                'message' => 'Device token tidak ditemukan.',
                'errors' => null,
                'data' => null,
            ], 404);
        }

        if ($deviceToken->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        $deviceToken->revoked_at = now();
        $deviceToken->save();

        return response()->json([
            'success' => true,
            'message' => 'Device token berhasil dicabut.',
            'data' => null,
        ], 200);
    }
}
