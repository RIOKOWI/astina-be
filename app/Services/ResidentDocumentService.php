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
use Illuminate\Support\Str;

class ResidentDocumentService
{
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
            // Remove existing KTP
            $existing = $resident->media()
                ->where('collection', Media::COLLECTION_KTP)
                ->first();

            if ($existing) {
                $this->deleteMediaFile($existing);
                $existing->delete();
            }

            $path = $this->storeFile($file, "residents/{$resident->id}/documents/ktp");

            try {
                $media = Media::create([
                    'model_type' => Resident::class,
                    'model_id' => $resident->id,
                    'collection' => Media::COLLECTION_KTP,
                    'disk' => 'public',
                    'path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                Log::info('KTP uploaded', [
                    'resident_id' => $resident->id,
                    'user_id' => $user->id,
                    'media_id' => $media->id,
                ]);

                return $this->formatMedia($media);
            } catch (\Throwable $e) {
                Storage::disk('public')->delete($path);
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
            // Remove existing KK
            $existing = $household->media()
                ->where('collection', Media::COLLECTION_KK)
                ->first();

            if ($existing) {
                $this->deleteMediaFile($existing);
                $existing->delete();
            }

            $path = $this->storeFile($file, "households/{$household->id}/documents/kk");

            try {
                $media = Media::create([
                    'model_type' => Household::class,
                    'model_id' => $household->id,
                    'collection' => Media::COLLECTION_KK,
                    'disk' => 'public',
                    'path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);

                Log::info('KK uploaded', [
                    'household_id' => $household->id,
                    'user_id' => $user->id,
                    'media_id' => $media->id,
                ]);

                return $this->formatMedia($media);
            } catch (\Throwable $e) {
                Storage::disk('public')->delete($path);
                throw $e;
            }
        });
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

    private function storeFile(UploadedFile $file, string $directory): string
    {
        $uuid = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $filename = "{$uuid}.{$extension}";

        return $file->storeAs($directory, $filename, 'public');
    }

    private function deleteMediaFile(Media $media): void
    {
        if (Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }
    }

    private function formatMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'file_size' => $media->file_size,
            'url' => Storage::disk($media->disk)->url($media->path),
        ];
    }
}
