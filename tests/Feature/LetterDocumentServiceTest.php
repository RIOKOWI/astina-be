<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Letter;
use App\Models\LetterField;
use App\Models\LetterFieldValue;
use App\Models\LetterType;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use App\Services\LetterDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Menguji jalur render sungguhan: Blade -> DOMPDF -> PDF.
 *
 * LetterApiTest sengaja me-mock LetterDocumentService supaya cepat, jadi
 * template Blade dan DOMPDF tidak tersentuh di sana. Test ini menutup celah itu.
 */
class LetterDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Letter $letter;

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        $wargaRole = Role::create(['name' => 'Warga', 'code' => 'warga']);

        $this->resident = Resident::factory()->create([
            'full_name' => 'Budi Santoso',
            'gender' => 'male',
            'birth_place' => 'Tangerang',
            'birth_date' => '1990-01-10',
            'religion' => 'islam',
            'marital_status' => 'married',
            'occupation' => 'Software Engineer',
        ]);

        $household = Household::factory()->create([
            'address' => 'Perum Kutabumi 7 Astina Blok G3/09, RT 005 RW 016',
        ]);
        $this->resident->households()->attach($household->id, ['relationship' => 'head']);

        $user = User::factory()->create([
            'resident_id' => $this->resident->id,
        ]);
        $user->roles()->attach($wargaRole);

        $letterType = LetterType::factory()->create(['code' => 'SKD']);
        $field = LetterField::factory()->create([
            'letter_type_id' => $letterType->id,
            'field_key' => 'keperluan',
        ]);

        $this->letter = Letter::factory()->create([
            'letter_type_id' => $letterType->id,
            'resident_id' => $this->resident->id,
            'submitted_by' => $user->id,
            'reference_no' => 'SKD/RT05/202609/000123',
            'status' => 'approved',
            'purpose' => 'Membuat KTP baru',
            'approved_at' => '2026-09-04 10:00:00',
        ]);

        LetterFieldValue::factory()->create([
            'letter_id' => $this->letter->id,
            'letter_field_id' => $field->id,
            'value' => 'Membuat KTP baru',
        ]);

        $this->letter->load(['resident.households', 'letterType', 'fieldValues.letterField']);
    }

    public function test_generates_valid_single_page_a5_pdf(): void
    {
        $document = app(LetterDocumentService::class)->generateForLetter($this->letter);

        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertStringEndsWith('.pdf', $document->file_name);
        $this->assertGreaterThan(0, $document->file_size);
        $this->assertTrue(Storage::disk('private')->exists($document->path));

        $bytes = Storage::disk('private')->get($document->path);
        $this->assertStringStartsWith('%PDF-', $bytes);

        // A5 portrait = 419.53 x 595.28 pt (148mm x 210mm).
        $this->assertMatchesRegularExpression('/MediaBox\s*\[\s*0(\.0+)?\s+0(\.0+)?\s+419\.\d+\s+595\.\d+\s*\]/', $bytes);

        // Surat normal harus tetap satu halaman.
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $bytes));
    }

    public function test_stores_pdf_in_private_storage_under_letter_id(): void
    {
        $document = app(LetterDocumentService::class)->generateForLetter($this->letter);

        $this->assertStringStartsWith('letters/generated/'.$this->letter->id.'/', $document->path);
        $this->assertSame($this->letter->id, $document->letter_id);
        $this->assertSame('final', $document->document_type);
    }

    public function test_rendered_html_contains_static_template_text_and_dynamic_data(): void
    {
        $html = $this->renderHtml();

        // Teks statis template
        $this->assertStringContainsString('RUKUN WARGA (RW) 016', $html);
        $this->assertStringContainsString('RUKUN TETANGGA (RT) 005', $html);
        $this->assertStringContainsString('PERUM KUTABUMI 7 ASTINA KELURAHAN SUKATANI', $html);
        $this->assertStringContainsString('Yang bertanda tangan di bawah ini Ketua RT.005 RW.016', $html);
        $this->assertStringContainsString('Adalah benar penduduk/warga kami', $html);
        $this->assertStringContainsString('Demikian surat pengantar ini dibuat', $html);
        $this->assertStringContainsString('Sukatani,', $html);
        $this->assertStringContainsString('Ketua RW. 016', $html);
        $this->assertStringContainsString('Ketua RT. 005', $html);
        $this->assertStringContainsString('KARMAN SUHENDRA', $html);
        $this->assertStringContainsString('GILANG CHOIRUR R.', $html);

        // Data dinamis
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('Laki-laki', $html);
        $this->assertStringContainsString('Tangerang, 10 Januari 1990', $html);
        $this->assertStringContainsString('Islam', $html);
        $this->assertStringContainsString('Software Engineer', $html);
        $this->assertStringContainsString('Menikah', $html);
        $this->assertStringContainsString('Blok G3/09', $html);
        $this->assertStringContainsString('Membuat KTP baru', $html);
        $this->assertStringContainsString('4 September 2026', $html);
    }

    public function test_embeds_logo_signature_and_stamp_as_data_uris(): void
    {
        $html = $this->renderHtml();

        $this->assertStringContainsString('data:image/webp;base64,', $html, 'Logo RT tidak ter-embed.');
        $this->assertSame(
            2,
            substr_count($html, 'data:image/png;base64,'),
            'Stempel dan tanda tangan harus ter-embed sebagai PNG data URI.'
        );

        // Tidak boleh membocorkan path filesystem ke dalam dokumen.
        $this->assertStringNotContainsString(storage_path(), $html);
        $this->assertStringNotContainsString(public_path(), $html);
    }

    public function test_a5_page_size_is_declared_in_css(): void
    {
        $html = $this->renderHtml();

        $this->assertStringContainsString('size: A5 portrait', $html);
        $this->assertStringContainsString('Times New Roman', $html);
    }

    public function test_purpose_is_escaped_to_prevent_html_injection(): void
    {
        $this->letter->fieldValues->first()->update([
            'value' => '<script>alert(1)</script>',
        ]);
        $this->letter->load('fieldValues.letterField');

        $html = $this->renderHtml();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * Form aslinya menyediakan ruang keperluan sekitar dua baris. Panjang
     * keperluan yang wajar (contoh panjang dari requirement ±130 karakter)
     * harus tetap satu halaman.
     */
    public function test_long_but_realistic_purpose_still_fits_on_one_page(): void
    {
        $this->letter->fieldValues->first()->update([
            'value' => 'Untuk keperluan administrasi pekerjaan dan pengurusan dokumen kependudukan di kantor kelurahan Sukatani Kecamatan Rajeg',
        ]);
        $this->letter->load('fieldValues.letterField');

        $document = app(LetterDocumentService::class)->generateForLetter($this->letter);
        $bytes = Storage::disk('private')->get($document->path);

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $bytes));
    }

    /**
     * Keperluan yang tidak wajar panjangnya boleh meluber ke halaman kedua,
     * tapi blok tanda tangan harus tetap utuh (tidak terpotong dua halaman).
     */
    public function test_overflowing_purpose_keeps_signature_block_unbreakable(): void
    {
        $this->letter->fieldValues->first()->update([
            'value' => str_repeat('pengurusan dokumen kependudukan dan administrasi warga ', 8),
        ]);
        $this->letter->load('fieldValues.letterField');

        $html = $this->renderHtml();
        $this->assertMatchesRegularExpression('/\.signatures\s*\{[^}]*page-break-inside:\s*avoid/s', $html);

        $document = app(LetterDocumentService::class)->generateForLetter($this->letter);
        $bytes = Storage::disk('private')->get($document->path);

        $this->assertStringStartsWith('%PDF-', $bytes);
        // Tetap terkendali: maksimal dua halaman, tidak beranak halaman kosong.
        $this->assertLessThanOrEqual(2, preg_match_all('/\/Type\s*\/Page[^s]/', $bytes));
    }

    public function test_original_docx_template_is_never_modified(): void
    {
        $template = Storage::disk('private')->path(config('letters.templates.surat_pengantar'));
        $before = md5_file($template);

        app(LetterDocumentService::class)->generateForLetter($this->letter);

        $this->assertSame($before, md5_file($template), 'Template DOCX master tidak boleh berubah.');
    }

    public function test_no_docx_is_generated_for_final_document(): void
    {
        $document = app(LetterDocumentService::class)->generateForLetter($this->letter);

        $this->assertStringNotContainsString('.docx', $document->path);
        $this->assertStringNotContainsString('.docx', $document->file_name);

        $files = Storage::disk('private')->allFiles('letters/generated/'.$this->letter->id);
        foreach ($files as $file) {
            $this->assertStringEndsWith('.pdf', $file);
        }
    }

    /**
     * Render Blade lewat data yang disiapkan service (prepareViewData private,
     * jadi diakses via reflection agar test tetap menguji data asli).
     */
    private function renderHtml(): string
    {
        $method = new \ReflectionMethod(LetterDocumentService::class, 'prepareViewData');
        $method->setAccessible(true);

        $data = $method->invoke(app(LetterDocumentService::class), $this->letter);

        return view('letters.surat-pengantar', $data)->render();
    }
}
