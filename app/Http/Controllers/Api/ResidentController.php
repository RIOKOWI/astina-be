<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\ResidentIndexRequest;
use App\Http\Resources\Resident\ResidentResource;
use App\Http\Resources\Resident\ResidentSummaryResource;
use App\Models\Resident;
use App\Services\ResidentService;
use Illuminate\Http\JsonResponse;

class ResidentController extends Controller
{
    public function __construct(
        private readonly ResidentService $residentService,
    ) {}

    public function index(ResidentIndexRequest $request): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat daftar warga.');
        }

        $params = $request->validated();
        $residents = $this->residentService->getList(
            $params['search'] ?? null,
            $params['status'] ?? null,
            $params['per_page'] ?? 15,
        );

        return response()->json([
            'success' => true,
            'message' => 'Data warga berhasil diambil.',
            'data' => ResidentSummaryResource::collection($residents),
            'meta' => [
                'current_page' => $residents->currentPage(),
                'per_page' => $residents->perPage(),
                'total' => $residents->total(),
                'last_page' => $residents->lastPage(),
            ],
        ]);
    }

    public function show(Resident $resident): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat data warga.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Data warga berhasil diambil.',
            'data' => new ResidentResource($resident),
            'meta' => null,
        ]);
    }
}
