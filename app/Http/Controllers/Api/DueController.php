<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Due\GenerateDueBillsRequest;
use App\Http\Requests\Due\StoreDueRequest;
use App\Http\Requests\Due\UpdateDueRequest;
use App\Http\Resources\Due\DueBillResource;
use App\Http\Resources\Due\DueResource;
use App\Models\Due;
use App\Services\DueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DueController extends Controller
{
    public function __construct(
        private readonly DueService $dueService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('rt')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        $includeInactive = $request->boolean('include_inactive', false);
        $perPage = $request->integer('per_page', 15);

        $dues = $this->dueService->getList($includeInactive, $perPage);

        $data = $dues->getCollection()->map(fn ($due) => new DueResource($due));

        return response()->json([
            'success' => true,
            'message' => 'Daftar iuran berhasil diambil.',
            'data' => [
                'data' => $data->toArray(),
            ],
            'meta' => [
                'current_page' => $dues->currentPage(),
                'last_page' => $dues->lastPage(),
                'per_page' => $dues->perPage(),
                'total' => $dues->total(),
            ],
        ]);
    }

    public function store(StoreDueRequest $request): JsonResponse
    {
        $due = $this->dueService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Iuran berhasil dibuat.',
            'data' => new DueResource($due),
            'meta' => null,
        ], 201);
    }

    public function show(Request $request, Due $due): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('rt')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        $due = $this->dueService->getDue($due);

        return response()->json([
            'success' => true,
            'message' => 'Detail iuran.',
            'data' => new DueResource($due),
            'meta' => null,
        ]);
    }

    public function update(UpdateDueRequest $request, Due $due): JsonResponse
    {
        $due = $this->dueService->update($due, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Iuran berhasil diperbarui.',
            'data' => new DueResource($due),
            'meta' => null,
        ]);
    }

    public function destroy(Request $request, Due $due): JsonResponse
    {
        $due = $this->dueService->deactivate($due);

        return response()->json([
            'success' => true,
            'message' => 'Iuran berhasil dinonaktifkan.',
            'data' => new DueResource($due),
            'meta' => null,
        ]);
    }

    public function generateDueBills(GenerateDueBillsRequest $request): JsonResponse
    {
        $created = $this->dueService->generateMonthlyDueBills(
            $request->integer('year'),
            $request->integer('month'),
        );

        return response()->json([
            'success' => true,
            'message' => "Tagihan bulanan berhasil dibuat untuk {$created} warga.",
            'data' => ['created' => $created],
            'meta' => null,
        ]);
    }

    public function dueBills(Due $due): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole('rt')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        $due->load('dueBills.resident');
        $bills = $due->dueBills()->paginate(15);
        $data = $bills->getCollection()->map(fn ($bill) => new DueBillResource($bill));

        return response()->json([
            'success' => true,
            'message' => 'Daftar tagihan berhasil diambil.',
            'data' => [
                'data' => $data->toArray(),
            ],
            'meta' => [
                'current_page' => $bills->currentPage(),
                'last_page' => $bills->lastPage(),
                'per_page' => $bills->perPage(),
                'total' => $bills->total(),
            ],
        ]);
    }
}
