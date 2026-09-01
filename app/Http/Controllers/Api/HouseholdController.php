<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Household\HouseholdResource;
use App\Models\Household;
use App\Services\HouseholdService;
use Illuminate\Http\JsonResponse;

class HouseholdController extends Controller
{
    public function __construct(
        private readonly HouseholdService $householdService,
    ) {}

    public function index(): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat daftar keluarga.');
        }

        $households = $this->householdService->getList();

        return response()->json([
            'success' => true,
            'message' => 'Data keluarga berhasil diambil.',
            'data' => HouseholdResource::collection($households),
            'meta' => [
                'current_page' => $households->currentPage(),
                'per_page' => $households->perPage(),
                'total' => $households->total(),
                'last_page' => $households->lastPage(),
            ],
        ]);
    }

    public function show(Household $household): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat data keluarga.');
        }

        $household = $this->householdService->getDetail($household);

        return response()->json([
            'success' => true,
            'message' => 'Data keluarga berhasil diambil.',
            'data' => new HouseholdResource($household),
            'meta' => null,
        ]);
    }
}
