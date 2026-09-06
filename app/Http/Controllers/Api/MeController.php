<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\UpdateMyResidentRequest;
use App\Http\Requests\Resident\UploadKkRequest;
use App\Http\Requests\Resident\UploadKtpRequest;
use App\Http\Resources\Household\HouseholdResource;
use App\Http\Resources\Resident\ResidentDocumentResource;
use App\Http\Resources\Resident\ResidentResource;
use App\Services\HouseholdService;
use App\Services\ResidentDocumentService;
use App\Services\ResidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeController extends Controller
{
    public function __construct(
        private readonly ResidentService $residentService,
        private readonly HouseholdService $householdService,
        private readonly ResidentDocumentService $documentService,
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

    public function updateResident(UpdateMyResidentRequest $request): JsonResponse
    {
        $user = auth()->user();
        $resident = $this->residentService->getAuthenticatedResident($user->id);

        if (! $resident) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum memiliki data resident.',
                'errors' => null,
                'data' => null,
            ], 422);
        }

        $resident = $this->residentService->updateOwnResident($resident, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data warga berhasil diperbarui.',
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

    public function documents(): JsonResponse
    {
        $user = auth()->user();
        $data = $this->documentService->getMyDocuments($user);

        if ($data === null) {
            return response()->json([
                'success' => true,
                'message' => 'Data dokumen berhasil diambil.',
                'data' => null,
                'meta' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data dokumen berhasil diambil.',
            'data' => new ResidentDocumentResource($data),
            'meta' => null,
        ]);
    }

    public function uploadKtp(UploadKtpRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user->resident_id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum memiliki data resident.',
                'errors' => null,
                'data' => null,
            ], 422);
        }

        $result = $this->documentService->uploadKtp($user, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'KTP berhasil diupload.',
            'data' => $result,
            'meta' => null,
        ], 200);
    }

    public function uploadKk(UploadKkRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user->resident_id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum memiliki data resident.',
                'errors' => null,
                'data' => null,
            ], 422);
        }

        try {
            $result = $this->documentService->uploadKk($user, $request->file('file'));
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'household')) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => null,
                    'data' => null,
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'KK berhasil diupload.',
            'data' => $result,
            'meta' => null,
        ], 200);
    }

    public function downloadKtp(): StreamedResponse
    {
        $user = auth()->user();
        $media = $this->documentService->downloadKtp($user);

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

    public function downloadKk(): StreamedResponse
    {
        $user = auth()->user();
        $media = $this->documentService->downloadKk($user);

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
