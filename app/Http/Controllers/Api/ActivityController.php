<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\ActivityIndexRequest;
use App\Http\Requests\Activity\StoreActivityRequest;
use App\Http\Requests\Activity\StoreAttachmentRequest;
use App\Http\Requests\Activity\UpdateActivityRequest;
use App\Http\Resources\Activity\ActivityAttachmentResource;
use App\Http\Resources\Activity\ActivityResource;
use App\Http\Resources\Activity\ActivitySummaryResource;
use App\Models\Activity;
use App\Models\ActivityAttachment;
use App\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __construct(
        private readonly ActivityService $activityService,
    ) {}

    public function index(ActivityIndexRequest $request): JsonResponse
    {
        $user = $request->user();
        $params = $request->validated();
        $perPage = $params['per_page'] ?? 15;

        if ($user->hasRole('rt')) {
            $activities = $this->activityService->getList(
                $params['search'] ?? null,
                $params['status'] ?? null,
                $perPage
            );
        } else {
            $activities = $this->activityService->getPublishedList(
                $params['search'] ?? null,
                $perPage
            );
        }

        $readStatusMap = $this->activityService->getReadStatusBatch($activities->getCollection(), $user->id);

        $summaryData = $activities->getCollection()->map(
            fn ($activity) => (new ActivitySummaryResource($activity))->additional(['read_status_map' => $readStatusMap])
        );

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas berhasil diambil.',
            'data' => $summaryData->toArray(),
            'meta' => [
                'current_page' => $activities->currentPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'last_page' => $activities->lastPage(),
            ],
        ]);
    }

    public function store(StoreActivityRequest $request): JsonResponse
    {
        $activity = $this->activityService->create(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas berhasil dibuat.',
            'data' => new ActivityResource($activity->load('attachments', 'creator')),
            'meta' => null,
        ], 201);
    }

    public function show(Request $request, Activity $activity): JsonResponse
    {
        $user = $request->user();

        // Warga can only view published activities
        if (! $user->hasRole('rt') && $activity->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Aktivitas tidak ditemukan.',
                'errors' => null,
                'data' => null,
            ], 404);
        }

        $activity->load('attachments', 'creator');

        $isRead = false;
        if ($user) {
            $isRead = $activity->reads()
                ->where('user_id', $user->id)
                ->exists();
        }

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas berhasil diambil.',
            'data' => (new ActivityResource($activity))->additional(['is_read' => $isRead]),
            'meta' => null,
        ]);
    }

    public function update(UpdateActivityRequest $request, Activity $activity): JsonResponse
    {
        $activity = $this->activityService->update($activity, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas berhasil diperbarui.',
            'data' => new ActivityResource($activity->load('attachments', 'creator')),
            'meta' => null,
        ]);
    }

    public function destroy(Request $request, Activity $activity): JsonResponse
    {
        if (! $request->user()->hasRole('rt')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus aktivitas.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        $this->activityService->delete($activity);

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas berhasil dihapus.',
            'data' => null,
            'meta' => null,
        ]);
    }

    public function read(Request $request, Activity $activity): JsonResponse
    {
        // Only allow reading published activities
        if ($activity->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Aktivitas tidak ditemukan.',
                'errors' => null,
                'data' => null,
            ], 404);
        }

        $this->activityService->markAsRead($activity, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Aktivitas berhasil ditandai telah dibaca.',
            'data' => null,
            'meta' => null,
        ]);
    }

    public function storeAttachment(StoreAttachmentRequest $request, Activity $activity): JsonResponse
    {
        $attachment = $this->activityService->storeAttachment($activity, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lampiran berhasil diupload.',
            'data' => new ActivityAttachmentResource($attachment),
            'meta' => null,
        ], 201);
    }

    public function destroyAttachment(Request $request, Activity $activity, ActivityAttachment $attachment): JsonResponse
    {
        if (! $request->user()->hasRole('rt')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghapus lampiran.',
                'errors' => null,
                'data' => null,
            ], 403);
        }

        if ($attachment->activity_id !== $activity->id) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran tidak ditemukan.',
                'errors' => null,
                'data' => null,
            ], 404);
        }

        $this->activityService->deleteAttachment($attachment);

        return response()->json([
            'success' => true,
            'message' => 'Lampiran berhasil dihapus.',
            'data' => null,
            'meta' => null,
        ]);
    }
}
