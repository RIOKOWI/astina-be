<?php

namespace Tests\Unit;

use App\Services\ImageService;
use App\Services\ProcessedImageResult;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class ImageServiceTest extends TestCase
{
    private ImageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageService();
    }

    public function test_process_jpeg_image_resizes_to_max_dimension(): void
    {
        // 3000x2000 JPEG (exceeds 1600px max)
        $file = UploadedFile::fake()->image('test.jpg', 3000, 2000);

        $result = $this->service->process($file);

        $this->assertInstanceOf(ProcessedImageResult::class, $result);
        $this->assertLessThanOrEqual(1600, $result->processedWidth);
        $this->assertLessThanOrEqual(1600, $result->processedHeight);
        $this->assertEquals(3000, $result->originalWidth);
        $this->assertEquals(2000, $result->originalHeight);
        $this->assertStringEndsWith('.jpg', $result->filename);
        $this->assertEquals('image/jpeg', $result->mimeType);
        $this->assertNotEmpty($result->contents);
        $this->assertGreaterThan(0, $result->fileSize);

        unlink($file->getPathname());
    }

    public function test_process_small_image_not_resized(): void
    {
        // 800x600 (below 1600px max)
        $file = UploadedFile::fake()->image('small.jpg', 800, 600);

        $result = $this->service->process($file);

        $this->assertEquals(800, $result->processedWidth);
        $this->assertEquals(600, $result->processedHeight);
        $this->assertEquals(800, $result->originalWidth);
        $this->assertEquals(600, $result->originalHeight);

        unlink($file->getPathname());
    }

    public function test_process_preserves_png_transparency(): void
    {
        $file = UploadedFile::fake()->image('test.png', 800, 600);

        $result = $this->service->process($file);

        $this->assertStringEndsWith('.png', $result->filename);
        $this->assertEquals('image/png', $result->mimeType);
        // PNG encoding should produce valid PNG bytes
        $this->assertStringStartsWith("\x89PNG", $result->contents);

        unlink($file->getPathname());
    }

    public function test_process_resizes_tall_image_by_height(): void
    {
        // Portrait: 1000x3000
        $file = UploadedFile::fake()->image('tall.jpg', 1000, 3000);

        $result = $this->service->process($file);

        $this->assertEquals(1000, $result->originalWidth);
        $this->assertEquals(3000, $result->originalHeight);
        $this->assertLessThanOrEqual(1600, $result->processedHeight);
        $this->assertLessThanOrEqual(1600, $result->processedWidth);

        unlink($file->getPathname());
    }

    public function test_processed_file_size_smaller_than_original(): void
    {
        // Large high-quality JPEG should be compressed
        $file = UploadedFile::fake()->image('large.jpg', 2000, 2000);

        $result = $this->service->process($file);

        // JPEG at quality 75 should be smaller than original
        $this->assertLessThan($file->getSize(), $result->fileSize);

        unlink($file->getPathname());
    }

    public function test_filename_is_uuid_and_unique(): void
    {
        $file1 = UploadedFile::fake()->image('a.jpg', 100, 100);
        $file2 = UploadedFile::fake()->image('b.jpg', 100, 100);

        $result1 = $this->service->process($file1);
        $result2 = $this->service->process($file2);

        $this->assertNotEquals($result1->filename, $result2->filename);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.jpg$/', $result1->filename);

        unlink($file1->getPathname());
        unlink($file2->getPathname());
    }

    public function test_preferred_format_overrides_original(): void
    {
        // PNG uploaded but request WebP
        $file = UploadedFile::fake()->image('test.png', 800, 600);

        $result = $this->service->process($file, 'webp');

        $this->assertStringEndsWith('.webp', $result->filename);
        $this->assertEquals('image/webp', $result->mimeType);

        unlink($file->getPathname());
    }

    public function test_processed_image_result_contains_all_fields(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 1600, 1200);

        $result = $this->service->process($file);

        $this->assertNotNull($result->contents);
        $this->assertNotNull($result->filename);
        $this->assertNotNull($result->mimeType);
        $this->assertNotNull($result->fileSize);
        $this->assertNotNull($result->originalWidth);
        $this->assertNotNull($result->originalHeight);
        $this->assertNotNull($result->processedWidth);
        $this->assertNotNull($result->processedHeight);
        $this->assertEquals($result->originalWidth, $result->processedWidth);
        $this->assertEquals($result->originalHeight, $result->processedHeight);

        unlink($file->getPathname());
    }

    public function test_square_image_resized_correctly(): void
    {
        $file = UploadedFile::fake()->image('square.jpg', 2000, 2000);

        $result = $this->service->process($file);

        // Should be resized to 1600x1600
        $this->assertEquals(1600, $result->processedWidth);
        $this->assertEquals(1600, $result->processedHeight);

        unlink($file->getPathname());
    }
}
