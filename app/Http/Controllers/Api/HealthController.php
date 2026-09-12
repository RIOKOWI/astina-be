<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'status' => 'healthy',
                'timestamp' => now()->toIso8601String(),
            ],
            'meta' => null,
        ]);
    }
}
