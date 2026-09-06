<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Media;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ResidentDocumentService
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    public function getMyDocuments(User $user): ?array
    {
        $resident = Resident::query()->find($user->resident_id);
        if (! $resident) {
            return null;
        }

        $ktp = $resident->media()
            ->where('collection', Media::COLLECTION_KTP)
            ->first();

        $currentHousehold = $this->getCurrentHousehold($resident);
        $kk = null;
        if ($currentHousehold) {
            $kk = $currentHousehold->media()
                ->where('collection', Media::COLLECTION_KK)
                ->first();
        }

        return [
            'resident' => [
                'id' => $resident->id,
                'nik' => $resident->nik,
                'full_name' => $resident->full_name,
            ],
            'ktp' => $ktp ? $this->formatMedia($ktp) : null,
            'household' => $currentHousehold ? [
                'id' => $currentHousehold->id,
                'no_kk' => $currentHousehold->no_kk,
            ] : null,
            'kk' => $kk ? $this->formatMedia($kk) : null,
        ];
    }

    public function uploadKtp(User $user, UploadedFile $file): array
    {
        $resident = Resident::query()->findOrFail($user->resident_id);

        return DB::transaction(function () use ($resident, $file, $user) {
            // Remove existing KTP first
            $existing = $resident->media()
                ->where('collection', Media::COLLECTION_KTP)
                ->first();

            $oldPath = $existing?->path;
            $oldDisk = $existing?->disk;

            if ($existing) {
                $existing->delete();
            }

            $directory = "residents/{$resident->id}/documents/ktp";
            $result = $this->imageService->process($file);
            $path = "{$directory}/{$result->filename}";

            try {
                Storage::disk('private')->put($path, $result->contents);

                $media = Media::create([
                    'model_type' => Resident::class,
                    'model_id' => $resident->id,
                    'collection' => Media::COLLECTION_KTP,
                    'disk' => 'private',
                    'path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $result->mimeType,
                    'file_size' => $result->fileSize,
                ]);

                // Delete old file after new one is safely stored
                if ($oldPath && $oldDisk) {
                    $this->deleteMediaFile($oldPath, $oldDisk);
                }

                Log::info('KTP uploaded', [
                    'resident_id' => $resident->id,
                    'user_id' => $user->id,
                    'media_id' => $media->id,
                    'original_size' => $file->getSize(),
                    'processed_size' => $result->fileSize,
                    'processed_mime' => $result->mimeType,
                    'width' => $result->processedWidth,
                    'height' => $result->processedHeight,
                    'feature' => 'ktp',
                ]);

                return $this->formatMedia($media);
            } catch (\Throwable $e) {
                Storage::disk('private')->delete($path);
                throw $e;
            }
        });
    }

    public function uploadKk(User $user, UploadedFile $file): array
    {
        $resident = Resident::query()->findOrFail($user->resident_id);
        $household = $this->getCurrentHousehold($resident);

        if (! $household) {
            abort(422, 'Anda belum memiliki household aktif. Silakan hubungi RT untuk penetapan household.');
        }

        return DB::transaction(function () use ($household, $file, $user) {
            // Remove existing KK first
            $existing = $household->media()
                ->where('collection', Media::COLLECTION_KK)
                ->first();

            $oldPath = $existing?->path;
            $oldDisk = $existing?->disk;

            if ($existing) {
                $existing->delete();
            }

            $directory = "households/{$household->id}/documents/kk";
            $result = $this->imageService->process($file);
            $path = "{$directory}/{$result->filename}";

            try {
                Storage::disk('private')->put($path, $result->contents);

                $media = Media::create([
                    'model_type' => Household::class,
                    'model_id' => $household->id,
                    'collection' => Media::COLLECTION_KK,
                    'disk' => 'private',
                    'path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $result->mimeType,
                    'file_size' => $result->fileSize,
                ]);

                // Delete old file after new one is safely stored
                if ($oldPath && $oldDisk) {
                    $this->deleteMediaFile($oldPath, $oldDisk);
                }

                Log::info('KK uploaded', [
                    'household_id' => $household->id,
                    'user_id' => $user->id,
                    'media_id' => $media->id,
                    'original_size' => $file->getSize(),
                    'processed_size' => $result->fileSize,
                    'processed_mime' => $result->mimeType,
                    'width' => $result->processedWidth,
                    'height' => $result->processedHeight,
                    'feature' => 'kk',
                ]);

                return $this->formatMedia($media);
            } catch (\Throwable $e) {
                Storage::disk('private')->delete($path);
                throw $e;
            }
        });
    }

    public function downloadKtp(User $user): Media
    {
        if (! $user->hasRole('warga')) {
            abort(403, 'Anda tidak memiliki akses ke endpoint ini.');
        }

        $resident = Resident::query()->find($user->resident_id);
        if (! $resident) {
            abort(404, 'Data resident tidak ditemukan.');
        }

        $ktp = $resident->media()
            ->where('collection', Media::COLLECTION_KTP)
            ->firstOrFail();

        if ($ktp->disk !== 'private' || ! Storage::disk('private')->exists($ktp->path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return $ktp;
    }

    public function downloadKk(User $user): Media
    {
        if (! $user->hasRole('warga')) {
            abort(403, 'Anda tidak memiliki akses ke endpoint ini.');
        }

        $resident = Resident::query()->find($user->resident_id);
        if (! $resident) {
            abort(404, 'Data resident tidak ditemukan.');
        }
        $household = $this->getCurrentHousehold($resident);

        if (! $household) {
            abort(404, 'Household tidak ditemukan.');
        }

        $kk = $household->media()
            ->where('collection', Media::COLLECTION_KK)
            ->firstOrFail();

        if ($kk->disk !== 'private' || ! Storage::disk('private')->exists($kk->path)) {
            abort(404, 'File tidak ditemukan.');
        }

        return $kk;
    }

    private function getCurrentHousehold(Resident $resident): ?Household
    {
        $households = $resident->households()
            ->wherePivot('is_current', true)
            ->wherePivotNull('resident_households.left_at')
            ->get();

        if ($households->count() > 1) {
            Log::warning('Multiple current households detected', [
                'resident_id' => $resident->id,
                'household_ids' => $households->pluck('id')->toArray(),
            ]);
        }

        return $households->first();
    }

    private function deleteMediaFile(string $path, string $disk): void
    {
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    private function formatMedia(Media $media): array
    {
        // Private files require authenticated download endpoint — no public URL
        return [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'file_size' => $media->file_size,
            'url' => null,
        ];
    }
}
