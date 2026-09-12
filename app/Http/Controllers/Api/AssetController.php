<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\AssetIndexRequest;
use App\Http\Requests\Asset\StoreAssetMovementRequest;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Resources\Asset\AssetMovementResource;
use App\Http\Resources\Asset\AssetResource;
use App\Http\Resources\Asset\AssetSummaryResource;
use App\Models\Asset;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetService $assetService,
    ) {}

    public function index(AssetIndexRequest $request): JsonResponse
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

        $params = $request->validated();

        $assets = $this->assetService->getList(
            $params['search'] ?? null,
            $params['category'] ?? null,
            $params['condition'] ?? null,
            $params['status'] ?? null,
            $params['per_page'] ?? 15,
        );

        $data = $assets->getCollection()->map(
            fn ($asset) => new AssetSummaryResource($asset)
        );

        return response()->json([
            'success' => true,
            'message' => 'Daftar asset berhasil diambil.',
            'data' => $data->toArray(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
                'last_page' => $assets->lastPage(),
            ],
        ]);
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        $asset = $this->assetService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Asset berhasil dibuat.',
            'data' => new AssetResource($asset->load('movements')),
            'meta' => null,
        ], 201);
    }

    public function show(Asset $asset): JsonResponse
    {
        $asset = $this->assetService->getDetail($asset);

        return response()->json([
            'success' => true,
            'message' => 'Detail asset berhasil diambil.',
            'data' => new AssetResource($asset),
            'meta' => null,
        ]);
    }

    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        $asset = $this->assetService->update($asset, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Asset berhasil diperbarui.',
            'data' => new AssetResource($asset),
            'meta' => null,
        ]);
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
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

        $asset = $this->assetService->deactivate($asset);

        return response()->json([
            'success' => true,
            'message' => 'Asset berhasil dinonaktifkan.',
            'data' => new AssetResource($asset),
            'meta' => null,
        ]);
    }

    public function movements(Request $request, Asset $asset): JsonResponse
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

        $perPage = (int) ($request->query('per_page') ?? 15);
        $perPage = min(max($perPage, 1), 50);

        $movements = $this->assetService->getMovements(
            $asset,
            $request->query('type'),
            $request->query('from'),
            $request->query('to'),
            $request->query('search'),
            $perPage,
        );

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pergerakan asset berhasil diambil.',
            'data' => AssetMovementResource::collection($movements->getCollection())->toArray($request),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
                'last_page' => $movements->lastPage(),
            ],
        ]);
    }

    public function storeMovement(StoreAssetMovementRequest $request, Asset $asset): JsonResponse
    {
        $movement = $this->assetService->createMovement(
            $asset,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Pergerakan asset berhasil dicatat.',
            'data' => new AssetMovementResource($movement->load('creator')),
            'meta' => null,
        ], 201);
    }
}
