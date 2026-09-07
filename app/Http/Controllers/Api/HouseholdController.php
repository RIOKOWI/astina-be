<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Household\AddHouseholdMemberRequest;
use App\Http\Requests\Household\StoreHouseholdRequest;
use App\Http\Requests\Household\UpdateHouseholdRequest;
use App\Http\Resources\Household\HouseholdResource;
use App\Models\Household;
use App\Models\Resident;
use App\Services\HouseholdService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class HouseholdController extends Controller
{
    public function __construct(
        private readonly HouseholdService $householdService,
    ) {}

    public function store(StoreHouseholdRequest $request): JsonResponse
    {
        $household = $this->householdService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data keluarga berhasil dibuat.',
            'data' => new HouseholdResource($household),
            'meta' => null,
        ], 201);
    }

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

    public function addMember(AddHouseholdMemberRequest $request, Household $household): JsonResponse
    {
        $resident = $this->householdService->addMember($household, $request->validated());
        $pivot = $resident->households()
            ->where('household_id', $household->id)
            ->wherePivot('is_current', true)
            ->first()
            ->pivot;

        return response()->json([
            'success' => true,
            'message' => 'Anggota keluarga berhasil ditambahkan.',
            'data' => [
                'id' => $resident->id,
                'full_name' => $resident->full_name,
                'phone' => $resident->phone,
                'relationship' => $pivot->relationship,
                'joined_at' => Carbon::parse($pivot->joined_at)->toDateString(),
            ],
            'meta' => null,
        ], 201);
    }

    public function removeMember(Household $household, Resident $resident): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses untuk mengeluarkan anggota keluarga.');
        }

        $this->householdService->removeMember($household, $resident);

        return response()->json([
            'success' => true,
            'message' => 'Anggota keluarga berhasil dikeluarkan dari household.',
            'data' => null,
            'meta' => null,
        ]);
    }

    public function update(UpdateHouseholdRequest $request, Household $household): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah data keluarga.');
        }

        $household = $this->householdService->update($household, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data keluarga berhasil diperbarui.',
            'data' => new HouseholdResource($household),
            'meta' => null,
        ]);
    }
}
