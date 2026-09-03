<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;

class ImageService
{
    private const MAX_DIMENSION = 1600;

    private const JPEG_QUALITY = 75;

    private const PNG_QUALITY = 75;

    public function process(UploadedFile $file, ?string $preferredFormat = null): ProcessedImageResult
    {
        $image = ImageManager::gd()->read($file->getPathname());

        // Auto-orient based on EXIF
        $image->orient();

        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Resize if exceeds max dimension, preserving aspect ratio
        $image = $this->resizeIfNeeded($image);

        $processedWidth = $image->width();
        $processedHeight = $image->height();

        // Determine output format
        $originalMime = $file->getMimeType();
        $format = $this->determineFormat($originalMime, $preferredFormat);
        $mimeType = $this->formatToMimeType($format);
        $quality = $this->getQuality($format);

        // Encode to binary
        $encoded = match ($format) {
            'jpeg' => $image->toJpg($quality),
            'png' => $image->toPng(),
            'webp' => $image->toWebp($quality),
            default => $image->toJpg($quality),
        };
        $contents = (string) $encoded;

        // Generate random filename
        $extension = $this->formatToExtension($format);
        $filename = Str::uuid()->toString().".{$extension}";

        return new ProcessedImageResult(
            contents: $contents,
            filename: $filename,
            mimeType: $mimeType,
            fileSize: strlen($contents),
            originalWidth: $originalWidth,
            originalHeight: $originalHeight,
            processedWidth: $processedWidth,
            processedHeight: $processedHeight,
        );
    }

    private function resizeIfNeeded(Image $image): Image
    {
        $width = $image->width();
        $height = $image->height();

        if ($width <= self::MAX_DIMENSION && $height <= self::MAX_DIMENSION) {
            return $image;
        }

        // Determine which dimension to constrain
        if ($width >= $height) {
            $newWidth = self::MAX_DIMENSION;
            $newHeight = (int) round($height * (self::MAX_DIMENSION / $width));

            return $image->resize($newWidth, $newHeight);
        }

        $newHeight = self::MAX_DIMENSION;
        $newWidth = (int) round($width * (self::MAX_DIMENSION / $height));

        return $image->resize($newWidth, $newHeight);
    }

    private function determineFormat(string $originalMime, ?string $preferredFormat): string
    {
        if ($preferredFormat !== null) {
            return $preferredFormat;
        }

        // Preserve PNG transparency as-is
        if ($originalMime === 'image/png') {
            return 'png';
        }

        // Default to JPEG for photos
        return 'jpeg';
    }

    private function formatToMimeType(string $format): string
    {
        return match ($format) {
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function formatToExtension(string $format): string
    {
        return match ($format) {
            'jpeg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };
    }

    private function getQuality(string $format): int
    {
        return match ($format) {
            'jpeg' => self::JPEG_QUALITY,
            'png' => self::PNG_QUALITY,
            'webp' => self::JPEG_QUALITY,
            default => self::JPEG_QUALITY,
        };
    }
}
