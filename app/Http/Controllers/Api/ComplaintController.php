<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\ComplaintIndexRequest;
use App\Http\Requests\Complaint\StoreComplaintAttachmentRequest;
use App\Http\Requests\Complaint\StoreComplaintCommentRequest;
use App\Http\Requests\Complaint\StoreComplaintRequest;
use App\Http\Requests\Complaint\UpdateComplaintStatusRequest;
use App\Http\Resources\Complaint\ComplaintAttachmentResource;
use App\Http\Resources\Complaint\ComplaintCommentResource;
use App\Http\Resources\Complaint\ComplaintResource;
use App\Http\Resources\Complaint\ComplaintSummaryResource;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintService $complaintService,
    ) {}

    public function index(ComplaintIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        $params = $request->validated();
        $perPage = $params['per_page'] ?? 15;

        if ($user->hasRole('rt')) {
            $complaints = $this->complaintService->getList(
                $params['search'] ?? null,
                $params['status'] ?? null,
                $params['category'] ?? null,
                $perPage
            );
        } else {
            $complaints = $this->complaintService->getWargaList(
                $user,
                $params['search'] ?? null,
                $params['status'] ?? null,
                $params['category'] ?? null,
                $perPage
            );
        }

        $data = $complaints->getCollection()->map(
            fn ($complaint) => new ComplaintSummaryResource($complaint)
        );

        return response()->json([
            'success' => true,
            'message' => 'Daftar laporan berhasil diambil.',
            'data' => $data->toArray(),
            'meta' => [
                'current_page' => $complaints->currentPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
                'last_page' => $complaints->lastPage(),
            ],
        ]);
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $complaint = $this->complaintService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Laporan berhasil dibuat.',
            'data' => new ComplaintResource($complaint->load('resident', 'attachments', 'assignee')),
            'meta' => null,
        ], 201);
    }

    public function show(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('rt') && $complaint->resident_id !== $user->resident_id) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan.',
                'errors' => null,
                'data' => null,
            ], 404);
        }

        $complaint->load(['resident', 'attachments', 'assignee', 'comments.user.roles']);

        return response()->json([
            'success' => true,
            'message' => 'Detail laporan berhasil diambil.',
            'data' => new ComplaintResource($complaint),
            'meta' => null,
        ]);
    }

    public function updateStatus(UpdateComplaintStatusRequest $request, Complaint $complaint): JsonResponse
    {
        $complaint = $this->complaintService->updateStatus($complaint, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Status laporan berhasil diperbarui.',
            'data' => new ComplaintResource($complaint->load('resident', 'attachments', 'assignee')),
            'meta' => null,
        ]);
    }

    public function storeAttachment(StoreComplaintAttachmentRequest $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('rt') && $complaint->resident_id !== $user->resident_id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengupload lampiran.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        $attachment = $this->complaintService->storeAttachment($complaint, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lampiran berhasil diupload.',
            'data' => new ComplaintAttachmentResource($attachment),
            'meta' => null,
        ], 201);
    }

    public function destroyAttachment(Request $request, Complaint $complaint, ComplaintAttachment $attachment): JsonResponse
    {
        $user = $request->user();

        if ($attachment->complaint_id !== $complaint->id) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran tidak ditemukan.',
                'errors' => null,
                'data' => null,
            ], 404);
        }

        if ($user->hasRole('rt')) {
            // RT boleh hapus lampiran apapun
        } elseif ($complaint->resident_id === $user->resident_id && $complaint->status === 'submitted') {
            // Warga boleh hapus lampiranmilik sendiri hanya jika complaint masih submitted
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus lampiran ini.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        $this->complaintService->deleteAttachment($attachment);

        return response()->json([
            'success' => true,
            'message' => 'Lampiran berhasil dihapus.',
            'data' => null,
            'meta' => null,
        ]);
    }

    public function comments(Request $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('rt') && $complaint->resident_id !== $user->resident_id) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan.',
                'errors' => null,
                'data' => null,
            ], 404);
        }

        $complaint->load('comments.user.roles');

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil diambil.',
            'data' => ComplaintCommentResource::collection($complaint->comments),
            'meta' => null,
        ]);
    }

    public function storeComment(StoreComplaintCommentRequest $request, Complaint $complaint): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('rt') && $complaint->resident_id !== $user->resident_id) {
            return response()->json([
                'success' => false,
                'message' => 'Laporan tidak ditemukan.',
                'errors' => null,
                'data' => null,
            ], 404);
        }

        if (in_array($complaint->status, ['closed', 'rejected'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menambahkan komentar pada pengaduan yang sudah ditutup atau ditolak.',
                'errors' => null,
                'data' => null,
            ], 422);
        }

        $comment = $this->complaintService->storeComment($complaint, $request->validated(), $user);

        return response()->json([
            'success' => true,
            'message' => 'Komentar berhasil ditambahkan.',
            'data' => new ComplaintCommentResource($comment->load('user.roles')),
            'meta' => null,
        ], 201);
    }
}
