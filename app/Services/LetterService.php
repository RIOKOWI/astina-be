<?php

namespace App\Services;

use App\Models\Letter;
use App\Models\LetterApproval;
use App\Models\LetterDocument;
use App\Models\LetterField;
use App\Models\LetterFieldValue;
use App\Models\LetterType;
use App\Models\Resident;
use App\Models\Signature;
use App\Models\Stamp;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LetterService
{
    private const VALID_TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['approved', 'rejected'],
        'approved' => ['completed'],
        'rejected' => [],
        'completed' => [],
    ];

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function getActiveLetterTypes(): Collection
    {
        return LetterType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getLetterType(int $id): LetterType
    {
        return LetterType::query()
            ->where('is_active', true)
            ->findOrFail($id);
    }

    public function createLetter(array $data, User $user): Letter
    {
        $resident = Resident::query()->findOrFail($user->resident_id);

        $letterType = LetterType::query()
            ->where('id', $data['letter_type_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $fields = $data['fields'] ?? [];

        // Validate field keys belong to this letter type
        $allowedFieldIds = LetterField::query()
            ->where('letter_type_id', $letterType->id)
            ->pluck('id', 'field_key');

        foreach (array_keys($fields) as $fieldKey) {
            if (! $allowedFieldIds->has($fieldKey)) {
                abort(422, "Field '{$fieldKey}' tidak ditemukan pada jenis surat ini.");
            }
        }

        // Validate required fields
        $requiredFields = LetterField::query()
            ->where('letter_type_id', $letterType->id)
            ->where('is_required', true)
            ->pluck('field_key')
            ->toArray();

        foreach ($requiredFields as $requiredKey) {
            $value = $fields[$requiredKey] ?? null;
            if ($value === null || $value === '') {
                abort(422, "Field '{$requiredKey}' wajib diisi.");
            }
        }

        $referenceNo = $this->generateReferenceNo($letterType);

        $letter = DB::transaction(function () use ($letterType, $resident, $user, $fields, $referenceNo, $data, $allowedFieldIds) {
            $letter = Letter::create([
                'reference_no' => $referenceNo,
                'letter_type_id' => $letterType->id,
                'resident_id' => $resident->id,
                'submitted_by' => $user->id,
                'purpose' => $data['purpose'] ?? null,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // Save field values (only provided ones)
            foreach ($fields as $fieldKey => $value) {
                $fieldId = $allowedFieldIds->get($fieldKey);
                if ($fieldId && $value !== null && $value !== '') {
                    LetterFieldValue::create([
                        'letter_id' => $letter->id,
                        'letter_field_id' => $fieldId,
                        'value' => (string) $value,
                    ]);
                }
            }

            Log::info('Letter submitted', [
                'letter_id' => $letter->id,
                'reference_no' => $letter->reference_no,
                'letter_type_id' => $letterType->id,
                'submitted_by' => $user->id,
            ]);

            return $letter;
        });

        $this->notifyRT($letter);

        return $letter->load(['letterType', 'resident', 'fieldValues.letterField']);
    }

    public function getMyLetters(User $user, ?string $status = null, ?int $letterTypeId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Letter::query()
            ->where('resident_id', $user->resident_id)
            ->with(['letterType'])
            ->orderByDesc('submitted_at');

        if ($status) {
            $query->where('status', $status);
        }

        if ($letterTypeId) {
            $query->where('letter_type_id', $letterTypeId);
        }

        return $query->paginate($perPage);
    }

    public function getPendingLetters(int $perPage = 15): LengthAwarePaginator
    {
        return Letter::query()
            ->where('status', 'submitted')
            ->with(['letterType', 'resident'])
            ->orderBy('submitted_at')
            ->paginate($perPage);
    }

    public function getLetter(Letter $letter, User $user): Letter
    {
        // Warga: only own letters
        // RT: all letters
        if (! $user->hasRole('rt') && $letter->resident_id !== $user->resident_id) {
            abort(403, 'Anda tidak memiliki akses ke surat ini.');
        }

        $letter->load(['letterType', 'resident', 'fieldValues.letterField', 'approvals.approver', 'documents', 'signatures.signer', 'stamps.stamper']);

        return $letter;
    }

    public function approveLetter(Letter $letter, User $rt): Letter
    {
        if (! $this->isValidTransition($letter->status, 'approved')) {
            abort(409, 'Surat tidak dapat disetujui pada status saat ini.');
        }

        DB::transaction(function () use ($letter, $rt) {
            $letter->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);

            LetterApproval::create([
                'letter_id' => $letter->id,
                'approved_by' => $rt->id,
                'action' => 'approved',
                'notes' => null,
                'acted_at' => now(),
                'created_at' => now(),
            ]);
        });

        Log::info('Letter approved', [
            'letter_id' => $letter->id,
            'reference_no' => $letter->reference_no,
            'approved_by' => $rt->id,
        ]);

        $this->notifyWarga($letter, 'letter_approved', 'Surat Disetujui', 'Surat Anda telah disetujui oleh RT dan siap ditandatangani.');

        return $letter->fresh()->load(['letterType', 'resident', 'fieldValues.letterField', 'approvals.approver']);
    }

    public function rejectLetter(Letter $letter, User $rt, string $reason): Letter
    {
        if (! $this->isValidTransition($letter->status, 'rejected')) {
            abort(409, 'Surat tidak dapat ditolak pada status saat ini.');
        }

        DB::transaction(function () use ($letter, $rt, $reason) {
            $letter->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            LetterApproval::create([
                'letter_id' => $letter->id,
                'approved_by' => $rt->id,
                'action' => 'rejected',
                'notes' => $reason,
                'acted_at' => now(),
                'created_at' => now(),
            ]);
        });

        Log::info('Letter rejected', [
            'letter_id' => $letter->id,
            'reference_no' => $letter->reference_no,
            'rejected_by' => $rt->id,
            'reason' => $reason,
        ]);

        $this->notifyWarga($letter, 'letter_rejected', 'Surat Ditolak', "Surat Anda ditolak. Alasan: {$reason}");

        return $letter->fresh()->load(['letterType', 'resident', 'fieldValues.letterField', 'approvals.approver']);
    }

    public function signLetter(Letter $letter, User $rt, UploadedFile $file): Signature
    {
        if ($letter->status !== 'approved') {
            abort(409, 'Surat harus berstatus approved sebelum dapat ditandatangani.');
        }

        $hasSignature = Signature::query()->where('letter_id', $letter->id)->exists();
        if ($hasSignature) {
            abort(409, 'Surat sudah ditandatangani.');
        }

        $uuid = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $filename = "{$uuid}.{$extension}";
        $path = $file->storeAs("signatures/{$letter->id}", $filename, 'public');

        try {
            $signatureHash = hash_file('sha256', $file->getRealPath());

            $signature = Signature::create([
                'letter_id' => $letter->id,
                'signed_by' => $rt->id,
                'signature_path' => $path,
                'signature_hash' => $signatureHash,
                'signed_at' => now(),
                'created_at' => now(),
            ]);

            Log::info('Letter signed', [
                'letter_id' => $letter->id,
                'reference_no' => $letter->reference_no,
                'signed_by' => $rt->id,
            ]);

            return $signature;
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }

    public function stampLetter(Letter $letter, User $rt, UploadedFile $file): Stamp
    {
        if ($letter->status !== 'approved') {
            abort(409, 'Surat harus berstatus approved sebelum dapat distempel.');
        }

        $hasSignature = Signature::query()->where('letter_id', $letter->id)->exists();
        if (! $hasSignature) {
            abort(409, 'Surat harus ditandatangani terlebih dahulu sebelum distempel.');
        }

        $hasStamp = Stamp::query()->where('letter_id', $letter->id)->exists();
        if ($hasStamp) {
            abort(409, 'Surat sudah distempel.');
        }

        $uuid = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $filename = "{$uuid}.{$extension}";
        $path = $file->storeAs("stamps/{$letter->id}", $filename, 'public');

        try {
            $stamp = Stamp::create([
                'letter_id' => $letter->id,
                'stamped_by' => $rt->id,
                'stamp_path' => $path,
                'stamped_at' => now(),
                'created_at' => now(),
            ]);

            $this->notifyWarga($letter, 'letter_stamped', 'Surat Distempel', 'Surat Anda telah distempel oleh RT.');

            // Check if we can complete the letter (both signed and stamped)
            $allDone = Signature::query()->where('letter_id', $letter->id)->exists()
                && Stamp::query()->where('letter_id', $letter->id)->exists();

            if ($allDone) {
                $letter->update(['status' => 'completed']);
                Log::info('Letter completed', [
                    'letter_id' => $letter->id,
                    'reference_no' => $letter->reference_no,
                ]);
                $this->notifyWarga($letter, 'letter_completed', 'Surat Selesai', 'Surat Anda telah lengkap dan siap digunakan.');
            }

            Log::info('Letter stamped', [
                'letter_id' => $letter->id,
                'reference_no' => $letter->reference_no,
                'stamped_by' => $rt->id,
            ]);

            return $stamp;
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }

    public function downloadDocument(Letter $letter, User $user): LetterDocument
    {
        // Check authorization
        if (! $user->hasRole('rt') && $letter->resident_id !== $user->resident_id) {
            abort(403, 'Anda tidak memiliki akses ke dokumen ini.');
        }

        if ($letter->status !== 'completed') {
            abort(409, 'Surat belum selesai diproses.');
        }

        $document = LetterDocument::query()
            ->where('letter_id', $letter->id)
            ->where('document_type', 'final')
            ->first();

        if (! $document) {
            abort(404, 'Dokumen tidak ditemukan.');
        }

        return $document;
    }

    public function generateDocument(Letter $letter, User $rt): LetterDocument
    {
        if ($letter->status !== 'approved') {
            abort(409, 'Surat harus berstatus approved sebelum dokumen dapat di-generate.');
        }

        $existing = LetterDocument::query()
            ->where('letter_id', $letter->id)
            ->where('document_type', 'final')
            ->first();

        if ($existing) {
            return $existing;
        }

        // Generate a simple placeholder document
        // In production, this would integrate with a PDF generation library
        $uuid = Str::uuid()->toString();
        $filename = "{$uuid}.pdf";
        $path = "letter-documents/{$letter->id}/{$filename}";

        // Create a minimal text-based document (placeholder for real PDF generation)
        $content = $this->generateLetterContent($letter);
        Storage::disk('public')->put($path, $content);

        try {
            return LetterDocument::create([
                'letter_id' => $letter->id,
                'document_type' => 'final',
                'path' => $path,
                'file_name' => "surat-{$letter->reference_no}.pdf",
                'mime_type' => 'application/pdf',
                'file_size' => strlen($content),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }

    private function generateLetterContent(Letter $letter): string
    {
        $resident = $letter->resident;
        $letterType = $letter->letterType;
        $fields = $letter->fieldValues->pluck('value', 'letterField.field_key');

        $content = "SURAT KETERANGAN\n";
        $content .= "===============================\n\n";
        $content .= "No: {$letter->reference_no}\n";
        $content .= "Jenis: {$letterType->name}\n\n";
        $content .= "DATA PEMOHON\n";
        $content .= "Nama: {$resident->full_name}\n";
        $content .= "NIK: {$resident->nik}\n";
        $content .= 'Alamat: '.($resident->address ?? '-')."\n\n";

        if ($letter->purpose) {
            $content .= "KEPERLUAN\n";
            $content .= "{$letter->purpose}\n\n";
        }

        if ($fields->isNotEmpty()) {
            $content .= "DATA SURAT\n";
            foreach ($fields as $key => $value) {
                $content .= ucfirst(str_replace('_', ' ', $key)).": {$value}\n";
            }
            $content .= "\n";
        }

        $content .= "===============================\n";
        $content .= "RT 005\n\n";
        $content .= 'Disetujui pada: '.($letter->approved_at ? $letter->approved_at->format('d/m/Y') : '-')."\n";
        $content .= "===============================\n";

        return $content;
    }

    private function generateReferenceNo(LetterType $letterType): string
    {
        $yearMonth = now()->format('Ym');
        $prefix = $letterType->code ?: 'SK';

        $lastLetter = Letter::query()
            ->where('reference_no', 'like', "{$prefix}/RT05/{$yearMonth}%")
            ->orderByDesc('id')
            ->first();

        if ($lastLetter) {
            $lastSeq = (int) Str::afterLast($lastLetter->reference_no, '/');
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return sprintf('%s/RT05/%s/%06d', $prefix, $yearMonth, $nextSeq);
    }

    private function isValidTransition(string $current, string $new): bool
    {
        $allowed = self::VALID_TRANSITIONS[$current] ?? [];

        return in_array($new, $allowed, true);
    }

    private function notifyRT(Letter $letter): void
    {
        $residentName = $letter->resident ? $letter->resident->full_name : 'Warga';
        $letterTypeName = $letter->letterType ? $letter->letterType->name : 'Surat';

        $this->notificationService->sendToUsersWithRole('rt', 'letter_submitted', 'Pengajuan Surat Baru', "{$residentName} mengajukan {$letterTypeName}.", [
            'type' => 'letter_submitted',
            'letter_id' => (string) $letter->id,
        ]);
    }

    private function notifyWarga(Letter $letter, string $type, string $title, string $body): void
    {
        $resident = $letter->resident;
        if (! $resident || ! $resident->user) {
            return;
        }

        $this->notificationService->sendToUser(
            $resident->user,
            $type,
            $title,
            $body,
            ['type' => $type, 'letter_id' => (string) $letter->id]
        );
    }
}
