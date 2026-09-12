<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Due\DueBillResource;
use App\Services\DueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyDueBillController extends Controller
{
    public function __construct(
        private readonly DueService $dueService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $residentId = $request->user()->resident_id;
        $perPage = $request->integer('per_page', 15);

        $dueBills = $this->dueService->getMyDueBills($residentId, $perPage);
        $data = $dueBills->getCollection()->map(fn ($bill) => new DueBillResource($bill));

        return response()->json([
            'success' => true,
            'message' => 'Daftar tagihan berhasil diambil.',
            'data' => [
                'data' => $data->toArray(),
            ],
            'meta' => [
                'current_page' => $dueBills->currentPage(),
                'last_page' => $dueBills->lastPage(),
                'per_page' => $dueBills->perPage(),
                'total' => $dueBills->total(),
            ],
        ]);
    }
}
