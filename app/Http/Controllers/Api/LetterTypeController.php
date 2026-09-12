<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Letter\StoreLetterFieldRequest;
use App\Http\Requests\Letter\StoreLetterTypeRequest;
use App\Http\Requests\Letter\UpdateLetterFieldRequest;
use App\Http\Requests\Letter\UpdateLetterTypeRequest;
use App\Http\Resources\Letter\LetterFieldResource;
use App\Http\Resources\Letter\LetterTypeResource;
use App\Models\LetterField;
use App\Models\LetterType;
use App\Services\LetterService;
use App\Services\LetterTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LetterTypeController extends Controller
{
    public function __construct(
        private readonly LetterService $letterService,
        private readonly LetterTypeService $letterTypeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive', false);
        $perPage = $request->integer('per_page', 15);

        // warga only sees active types (existing behavior)
        if (! $request->user()?->hasRole('rt')) {
            $letterTypes = $this->letterService->getActiveLetterTypes();

            return response()->json([
                'success' => true,
                'message' => 'Daftar jenis surat berhasil diambil.',
                'data' => LetterTypeResource::collection($letterTypes)->toArray($request),
                'meta' => null,
            ]);
        }

        // RT sees all (active + inactive) with pagination
        $letterTypes = $this->letterTypeService->getList($includeInactive, $perPage);
        $data = $letterTypes->getCollection()->map(fn ($lt) => new LetterTypeResource($lt));

        return response()->json([
            'success' => true,
            'message' => 'Daftar jenis surat berhasil diambil.',
            'data' => [
                'data' => $data->toArray(),
            ],
            'meta' => [
                'current_page' => $letterTypes->currentPage(),
                'last_page' => $letterTypes->lastPage(),
                'per_page' => $letterTypes->perPage(),
                'total' => $letterTypes->total(),
            ],
        ]);
    }

    public function show(Request $request, LetterType $letterType): JsonResponse
    {
        // warga cannot see inactive types
        if (! $request->user()?->hasRole('rt') && ! $letterType->is_active) {
            abort(404);
        }

        $letterType->load(['fields' => fn ($q) => $q->orderBy('sort_order')]);

        return response()->json([
            'success' => true,
            'message' => 'Detail jenis surat berhasil diambil.',
            'data' => new LetterTypeResource($letterType),
            'meta' => null,
        ]);
    }

    public function store(StoreLetterTypeRequest $request): JsonResponse
    {
        $letterType = $this->letterTypeService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Jenis surat berhasil dibuat.',
            'data' => new LetterTypeResource($letterType),
            'meta' => null,
        ], 201);
    }

    public function update(UpdateLetterTypeRequest $request, LetterType $letterType): JsonResponse
    {
        $letterType = $this->letterTypeService->update($letterType, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Jenis surat berhasil diperbarui.',
            'data' => new LetterTypeResource($letterType),
            'meta' => null,
        ]);
    }

    public function destroy(Request $request, LetterType $letterType): JsonResponse
    {
        if (! $request->user()?->hasRole('rt')) {
            abort(403);
        }
        $letterType = $this->letterTypeService->deactivate($letterType);

        return response()->json([
            'success' => true,
            'message' => 'Jenis surat berhasil dinonaktifkan.',
            'data' => new LetterTypeResource($letterType),
            'meta' => null,
        ]);
    }

    public function storeField(StoreLetterFieldRequest $request, LetterType $letterType): JsonResponse
    {
        $field = $this->letterTypeService->createField($letterType, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Field berhasil ditambahkan.',
            'data' => new LetterFieldResource($field),
            'meta' => null,
        ], 201);
    }

    public function updateField(UpdateLetterFieldRequest $request, LetterType $letterType, LetterField $field): JsonResponse
    {
        if ($field->letter_type_id !== $letterType->id) {
            abort(404);
        }
        $field = $this->letterTypeService->updateField($letterType, $field, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Field berhasil diperbarui.',
            'data' => new LetterFieldResource($field),
            'meta' => null,
        ]);
    }

    public function destroyField(Request $request, LetterType $letterType, LetterField $field): JsonResponse
    {
        if (! $request->user()?->hasRole('rt')) {
            abort(403);
        }
        if ($field->letter_type_id !== $letterType->id) {
            abort(404);
        }
        $this->letterTypeService->deleteField($letterType, $field);

        return response()->json([
            'success' => true,
            'message' => 'Field berhasil dihapus.',
            'data' => null,
            'meta' => null,
        ]);
    }
}
