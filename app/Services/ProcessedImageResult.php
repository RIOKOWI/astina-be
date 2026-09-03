<?php

namespace App\Services;

readonly class ProcessedImageResult
{
    public function __construct(
        public string $contents,
        public string $filename,
        public string $mimeType,
        public int $fileSize,
        public int $originalWidth,
        public int $originalHeight,
        public int $processedWidth,
        public int $processedHeight,
    ) {}
}
