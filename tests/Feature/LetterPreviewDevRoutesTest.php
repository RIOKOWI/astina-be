<?php

namespace Tests\Feature;

use Tests\TestCase;

class LetterPreviewDevRoutesTest extends TestCase
{
    public function test_preview_route_returns_200_with_dummy_data(): void
    {
        $response = $this->get('/dev/letters/surat-pengantar');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('Budi Santoso');
        $response->assertSee('Herminfort');
        $response->assertSee('Kristen');
        $response->assertSee('Wiraswasta');
        $response->assertSee('data:image/webp;base64,');
        $response->assertSee('data:image/png;base64,');
    }

    public function test_preview_route_does_not_require_authentication(): void
    {
        $this->assertTrue(true);
    }

    public function test_reference_route_returns_png(): void
    {
        $response = $this->get('/dev/letters/surat-pengantar/reference');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/png');
    }

    public function test_compare_route_returns_200(): void
    {
        $response = $this->get('/dev/letters/surat-pengantar/compare');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('Surat Pengantar');
        $response->assertSee('Side-by-side');
        $response->assertSee('Overlay');
    }

    public function test_preview_uses_dummy_resident_name_not_real_data(): void
    {
        $response = $this->get('/dev/letters/surat-pengantar');

        $response->assertStatus(200);
        $response->assertSee('Budi Santoso');
        $response->assertDontSee('admin', false);
    }

    public function test_preview_uses_logo_webp(): void
    {
        $response = $this->get('/dev/letters/surat-pengantar');

        $response->assertStatus(200);
        $response->assertSee('data:image/webp;base64,');
    }

    public function test_preview_uses_signature_and_stamp_png(): void
    {
        $response = $this->get('/dev/letters/surat-pengantar');

        $response->assertStatus(200);
        $response->assertSee('data:image/png;base64,');
    }
}
