<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityAttachment;
use App\Models\ActivityRead;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ActivityService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly ImageService $imageService,
    ) {}

    public function getList(?string $search = null, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Activity::query()
            ->with('attachments')
            ->withCount('reads')
            ->withCount('attachments')
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function getPublishedList(?string $search = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Activity::query()
            ->where('status', 'published')
            ->with('attachments')
            ->withCount('attachments')
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    public function getDetail(Activity $activity, ?User $user = null): array
    {
        $activity->load('attachments');

        $isRead = false;
        if ($user) {
            $isRead = ActivityRead::where('activity_id', $activity->id)
                ->where('user_id', $user->id)
                ->exists();
        }

        return [
            'activity' => $activity,
            'is_read' => $isRead,
        ];
    }

    public function create(array $data, User $user): Activity
    {
        $activity = Activity::create([
            'created_by' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'start_at' => $data['start_at'] ?? null,
            'end_at' => $data['end_at'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);

        if ($activity->status === 'published') {
            $this->dispatchNotification($activity);
        }

        return $activity;
    }

    public function update(Activity $activity, array $data): Activity
    {
        $wasPublished = $activity->status === 'published';

        $activity->update([
            'title' => $data['title'] ?? $activity->title,
            'description' => $data['description'] ?? $activity->description,
            'location' => $data['location'] ?? $activity->location,
            'start_at' => $data['start_at'] ?? $activity->start_at,
            'end_at' => $data['end_at'] ?? $activity->end_at,
            'status' => $data['status'] ?? $activity->status,
        ]);

        // Dispatch notification if newly published
        if ($activity->status === 'published' && ! $wasPublished) {
            $this->dispatchNotification($activity);
        }

        return $activity->fresh();
    }

    public function delete(Activity $activity): void
    {
        // Delete attachments files
        foreach ($activity->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
        }

        $activity->delete();
    }

    public function storeAttachment(Activity $activity, array $data): ActivityAttachment
    {
        $file = $data['file'];
        $directory = 'activities';
        $isImage = in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true);

        if ($isImage) {
            $result = $this->imageService->process($file);
            $path = "{$directory}/{$result->filename}";
            $mimeType = $result->mimeType;
            $fileSize = $result->fileSize;
        } else {
            // PDF: store directly without processing
            $filename = Str::uuid()->toString().'.pdf';
            $path = "{$directory}/{$filename}";
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();
        }

        try {
            if ($isImage) {
                Storage::disk('public')->put($path, $result->contents);
            } else {
                Storage::disk('public')->put($path, file_get_contents($file->getPathname()));
            }

            return ActivityAttachment::create([
                'activity_id' => $activity->id,
                'path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }

    public function deleteAttachment(ActivityAttachment $attachment): void
    {
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
    }

    public function markAsRead(Activity $activity, User $user): ActivityRead
    {
        return ActivityRead::firstOrCreate(
            [
                'activity_id' => $activity->id,
                'user_id' => $user->id,
            ],
            [
                'read_at' => now(),
            ]
        );
    }

    public function getReadStatusBatch(Collection $activities, int $userId): array
    {
        $activityIds = $activities->pluck('id')->toArray();

        $readActivityIds = ActivityRead::whereIn('activity_id', $activityIds)
            ->where('user_id', $userId)
            ->pluck('activity_id')
            ->flip()
            ->toArray();

        return $readActivityIds;
    }

    private function dispatchNotification(Activity $activity): void
    {
        $this->notificationService->sendToAllActiveUsers(
            'activity_created',
            'Aktivitas RT Baru',
            $activity->title,
            ['type' => 'activity_created', 'activity_id' => (string) $activity->id]
        );
    }
}
