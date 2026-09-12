<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\ResidentIndexRequest;
use App\Http\Requests\Resident\StoreResidentRequest;
use App\Http\Requests\Resident\UpdateResidentRequest;
use App\Http\Resources\Resident\ResidentAdminResource;
use App\Http\Resources\Resident\ResidentSummaryResource;
use App\Models\Resident;
use App\Services\ResidentDocumentService;
use App\Services\ResidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResidentController extends Controller
{
    public function __construct(
        private readonly ResidentService $residentService,
        private readonly ResidentDocumentService $documentService,
    ) {}

    public function store(StoreResidentRequest $request): JsonResponse
    {
        $resident = $this->residentService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data warga berhasil ditambahkan.',
            'data' => new ResidentAdminResource($resident),
            'meta' => null,
        ], 201);
    }

    public function index(ResidentIndexRequest $request): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat daftar warga.');
        }

        $params = $request->validated();
        $residents = $this->residentService->getList(
            $params['search'] ?? null,
            $params['status'] ?? null,
            $params['has_account'] ?? null,
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

        $resident = $this->residentService->getDetail($resident);

        return response()->json([
            'success' => true,
            'message' => 'Data warga berhasil diambil.',
            'data' => new ResidentAdminResource($resident),
            'meta' => null,
        ]);
    }

    public function update(UpdateResidentRequest $request, Resident $resident): JsonResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses untuk mengubah data warga.');
        }

        $resident = $this->residentService->updateResident($resident, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data warga berhasil diperbarui.',
            'data' => new ResidentAdminResource($resident),
            'meta' => null,
        ]);
    }

    public function downloadKtp(Resident $resident): StreamedResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat data warga.');
        }

        $media = $this->documentService->downloadKtpForResident($resident->id);

        $stream = Storage::disk('private')->readStream($media->path);

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);
            },
            200,
            [
                'Content-Type' => $media->mime_type,
                'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
                'Content-Length' => $media->file_size,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function downloadKk(Resident $resident): StreamedResponse
    {
        if (! auth()->user()->hasRole('rt')) {
            abort(403, 'Anda tidak memiliki akses untuk melihat data warga.');
        }

        $media = $this->documentService->downloadKkForResident($resident->id);

        $stream = Storage::disk('private')->readStream($media->path);

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);
            },
            200,
            [
                'Content-Type' => $media->mime_type,
                'Content-Disposition' => 'inline; filename="'.$media->file_name.'"',
                'Content-Length' => $media->file_size,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
