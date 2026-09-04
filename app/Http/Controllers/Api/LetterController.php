<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Letter\RejectLetterRequest;
use App\Http\Requests\Letter\SignLetterRequest;
use App\Http\Requests\Letter\StampLetterRequest;
use App\Http\Requests\Letter\StoreLetterRequest;
use App\Http\Resources\Letter\LetterResource;
use App\Models\Letter;
use App\Services\LetterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LetterController extends Controller
{
    public function __construct(
        private readonly LetterService $letterService,
    ) {}

    public function myLetters(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->integer('per_page', 15);

        $letters = $this->letterService->getMyLetters(
            $user,
            $request->query('status'),
            $request->query('letter_type_id') ? (int) $request->query('letter_type_id') : null,
            $perPage,
        );

        $data = $letters->getCollection()->map(fn ($letter) => new LetterResource($letter));

        return response()->json([
            'success' => true,
            'message' => 'Daftar surat berhasil diambil.',
            'data' => [
                'data' => $data->toArray(),
            ],
            'meta' => [
                'current_page' => $letters->currentPage(),
                'last_page' => $letters->lastPage(),
                'per_page' => $letters->perPage(),
                'total' => $letters->total(),
            ],
        ]);
    }

    public function store(StoreLetterRequest $request): JsonResponse
    {
        $letter = $this->letterService->createLetter(
            $request->validated(),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan surat berhasil dikirim.',
            'data' => new LetterResource($letter),
            'meta' => null,
        ], 201);
    }

    public function pending(Request $request): JsonResponse
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

        $perPage = $request->integer('per_page', 15);
        $letters = $this->letterService->getPendingLetters($perPage);
        $data = $letters->getCollection()->map(fn ($letter) => new LetterResource($letter));

        return response()->json([
            'success' => true,
            'message' => 'Daftar surat pending berhasil diambil.',
            'data' => [
                'data' => $data->toArray(),
            ],
            'meta' => [
                'current_page' => $letters->currentPage(),
                'last_page' => $letters->lastPage(),
                'per_page' => $letters->perPage(),
                'total' => $letters->total(),
            ],
        ]);
    }

    public function show(Request $request, Letter $letter): JsonResponse
    {
        $letter = $this->letterService->getLetter($letter, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Detail surat berhasil diambil.',
            'data' => new LetterResource($letter),
            'meta' => null,
        ]);
    }

    public function approve(Request $request, Letter $letter): JsonResponse
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

        $letter = $this->letterService->approveLetter($letter, $user);

        return response()->json([
            'success' => true,
            'message' => 'Surat berhasil disetujui dan dokumen telah dibuat.',
            'data' => new LetterResource($letter->load('documents')),
            'meta' => null,
        ]);
    }

    public function reject(RejectLetterRequest $request, Letter $letter): JsonResponse
    {
        $letter = $this->letterService->rejectLetter(
            $letter,
            $request->user(),
            $request->string('reason')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Surat berhasil ditolak.',
            'data' => new LetterResource($letter),
            'meta' => null,
        ]);
    }

    public function sign(SignLetterRequest $request, Letter $letter): JsonResponse
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

        $signature = $this->letterService->signLetter($letter, $user, $request->file('signature_image'));

        return response()->json([
            'success' => true,
            'message' => 'Tanda tangan berhasil disimpan.',
            'data' => [
                'signed_at' => $signature->signed_at?->toIso8601String(),
                'signed_by' => $signature->signed_by,
            ],
            'meta' => null,
        ], 201);
    }

    public function stamp(StampLetterRequest $request, Letter $letter): JsonResponse
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

        $stamp = $this->letterService->stampLetter($letter, $user, $request->file('stamp_image'));

        $letter->refresh()->load(['letterType', 'resident', 'fieldValues.letterField', 'approvals.approver', 'signatures.signer', 'stamps.stamper']);

        return response()->json([
            'success' => true,
            'message' => 'Stempel berhasil disimpan.',
            'data' => new LetterResource($letter),
            'meta' => null,
        ]);
    }

    public function document(Request $request, Letter $letter): StreamedResponse|JsonResponse
    {
        $document = $this->letterService->downloadDocument($letter, $request->user());

        if (! Storage::disk('private')->exists($document->path)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan.',
                'errors' => null,
                'data' => null,
            ], 404);
        }

        $stream = Storage::disk('private')->readStream($document->path);

        return response()->stream(
            function () use ($stream) {
                fpassthru($stream);
            },
            200,
            [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => 'attachment; filename="'.$document->file_name.'"',
                'Content-Length' => $document->file_size,
            ]
        );
    }
}
