<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Household\HouseholdResource;
use App\Http\Resources\Resident\ResidentResource;
use App\Services\HouseholdService;
use App\Services\ResidentService;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    public function __construct(
        private readonly ResidentService $residentService,
        private readonly HouseholdService $householdService,
    ) {}

    public function resident(): JsonResponse
    {
        $user = auth()->user();
        $resident = $this->residentService->getAuthenticatedResident($user->id);

        if (! $resident) {
            return response()->json([
                'success' => true,
                'message' => 'Data warga berhasil diambil.',
                'data' => null,
                'meta' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data warga berhasil diambil.',
            'data' => new ResidentResource($resident),
            'meta' => null,
        ]);
    }

    public function household(): JsonResponse
    {
        $user = auth()->user();
        $resident = $this->residentService->getAuthenticatedResident($user->id);

        if (! $resident) {
            return response()->json([
                'success' => true,
                'message' => 'Data keluarga berhasil diambil.',
                'data' => null,
                'meta' => null,
            ]);
        }

        $household = $this->householdService->getCurrentHousehold($resident);

        return response()->json([
            'success' => true,
            'message' => 'Data keluarga berhasil diambil.',
            'data' => $household ? new HouseholdResource($household) : null,
            'meta' => null,
        ]);
    }
}
