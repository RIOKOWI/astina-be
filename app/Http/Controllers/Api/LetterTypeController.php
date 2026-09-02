<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Letter\LetterTypeResource;
use App\Services\LetterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LetterTypeController extends Controller
{
    public function __construct(
        private readonly LetterService $letterService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $letterTypes = $this->letterService->getActiveLetterTypes();

        return response()->json([
            'success' => true,
            'message' => 'Daftar jenis surat berhasil diambil.',
            'data' => LetterTypeResource::collection($letterTypes)->toArray($request),
            'meta' => null,
        ]);
    }

    public function show(Request $request, int $letterType): JsonResponse
    {
        $letterType = $this->letterService->getLetterType($letterType);
        $letterType->load(['fields' => fn ($q) => $q->orderBy('sort_order')]);

        return response()->json([
            'success' => true,
            'message' => 'Detail jenis surat berhasil diambil.',
            'data' => new LetterTypeResource($letterType),
            'meta' => null,
        ]);
    }
}
