<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintComment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ComplaintService
{
    private const VALID_TRANSITIONS = [
        'submitted' => ['reviewed', 'rejected'],
        'reviewed' => ['in_progress', 'rejected'],
        'in_progress' => ['resolved'],
        'resolved' => ['closed'],
        'closed' => [],
        'rejected' => [],
    ];

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function getList(?string $search = null, ?string $status = null, ?string $category = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Complaint::query()
            ->with(['resident', 'attachments', 'assignee'])
            ->withCount('attachments')
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($category) {
            $query->where('category', $category);
        }

        return $query->paginate($perPage);
    }

    public function getWargaList(User $user, ?string $search = null, ?string $status = null, ?string $category = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Complaint::query()
            ->where('resident_id', $user->resident_id)
            ->with(['attachments', 'assignee'])
            ->withCount('attachments')
            ->orderByDesc('created_at');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($category) {
            $query->where('category', $category);
        }

        return $query->paginate($perPage);
    }

    public function create(array $data, User $user): Complaint
    {
        $lastComplaint = Complaint::orderByDesc('id')->first();
        $nextNumber = $lastComplaint ? ((int) Str::afterLast($lastComplaint->reference_no, '-') + 1) : 1;

        $complaint = Complaint::create([
            'resident_id' => $user->resident_id,
            'reference_no' => 'CMP-'.date('Y').'-'.str_pad($nextNumber, 6, '0', STR_PAD_LEFT),
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'category' => $data['category'],
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        Log::info('Complaint created', ['complaint_id' => $complaint->id, 'reference_no' => $complaint->reference_no]);

        $this->dispatchNotificationToRT($complaint);

        return $complaint;
    }

    public function updateStatus(Complaint $complaint, array $data): Complaint
    {
        $newStatus = $data['status'];

        if (! $this->isValidTransition($complaint->status, $newStatus)) {
            abort(409, 'Transisi status tidak valid.');
        }

        if ($newStatus === 'rejected' && empty($data['rejection_reason'])) {
            abort(422, 'Alasan penolakan wajib diisi.');
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'rejected') {
            $updateData['rejection_reason'] = $data['rejection_reason'];
        }

        if ($newStatus === 'reviewed') {
            $updateData['approved_at'] = now();
        }

        if ($newStatus === 'resolved') {
            $updateData['resolved_at'] = now();
        }

        if (isset($data['assigned_to'])) {
            $updateData['assigned_to'] = $data['assigned_to'];
        }

        $complaint->update($updateData);

        Log::info('Complaint status updated', [
            'complaint_id' => $complaint->id,
            'old_status' => $complaint->getOriginal('status'),
            'new_status' => $newStatus,
        ]);

        $this->dispatchStatusNotification($complaint, $newStatus);

        return $complaint->fresh();
    }

    public function storeAttachment(Complaint $complaint, array $data): ComplaintAttachment
    {
        $file = $data['file'];
        $uuid = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $filename = "{$uuid}.{$extension}";
        $path = $file->storeAs("complaints/{$complaint->id}", $filename, 'public');

        try {
            return ComplaintAttachment::create([
                'complaint_id' => $complaint->id,
                'path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }

    public function deleteAttachment(ComplaintAttachment $attachment): void
    {
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
    }

    public function storeComment(Complaint $complaint, array $data, User $user): ComplaintComment
    {
        return ComplaintComment::create([
            'complaint_id' => $complaint->id,
            'user_id' => $user->id,
            'comment' => $data['comment'],
        ]);
    }

    private function isValidTransition(string $current, string $new): bool
    {
        $allowed = self::VALID_TRANSITIONS[$current] ?? [];

        return in_array($new, $allowed, true);
    }

    private function dispatchNotificationToRT(Complaint $complaint): void
    {
        $this->notificationService->sendToAllActiveUsers(
            'complaint_created',
            'Laporan Baru',
            "Laporan {$complaint->reference_no}: {$complaint->title}",
            ['type' => 'complaint_created', 'complaint_id' => (string) $complaint->id]
        );
    }

    private function dispatchStatusNotification(Complaint $complaint, string $newStatus): void
    {
        $statusLabels = [
            'reviewed' => 'sedang ditinjau',
            'in_progress' => 'sedang ditangani',
            'resolved' => 'telah resolved',
            'closed' => 'ditutup',
            'rejected' => 'ditolak',
        ];

        $title = 'Laporan Diperbarui';
        $label = $statusLabels[$newStatus] ?? $newStatus;
        $body = "Laporan {$complaint->reference_no} {$label}.";

        $resident = $complaint->resident;
        if ($resident && $resident->user) {
            $this->notificationService->sendToUser(
                $resident->user,
                'complaint_status_changed',
                $title,
                $body,
                ['type' => 'complaint_status_changed', 'complaint_id' => (string) $complaint->id]
            );
        }
    }
}
