<?php

namespace App\Services;

use App\Models\Letter;
use App\Models\LetterDocument;
use App\Models\Resident;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LetterDocumentService
{
    private string $workDir;

    public function __construct()
    {
        $this->workDir = storage_path('framework/tmp/letters/'.Str::uuid()->toString());
    }

    public function generateForLetter(Letter $letter): LetterDocument
    {
        mkdir($this->workDir, 0755, true);

        try {
            // Step 1: Prepare view data
            $viewData = $this->prepareViewData($letter);

            // Step 2: Render Blade template to HTML string
            $html = view('letters.surat-pengantar', $viewData)->render();

            // Step 3: Generate PDF via DOMPDF
            $pdfPath = $this->generatePdf($html);

            // Step 4: Validate PDF
            $this->validatePdf($pdfPath);

            // Step 5: Store final PDF
            $storedPath = $this->storePdf($pdfPath, $letter);

            // Step 6: Create LetterDocument record
            $document = $this->createLetterDocument($letter, $storedPath);
        } finally {
            $this->cleanupWorkDir();
        }

        return $document;
    }

    protected function resolveTemplateKey(Letter $letter): string
    {
        $code = $letter->letterType->code ?? 'SKD';

        return match (strtolower($code)) {
            default => config('letters.templates.surat_pengantar'),
        };
    }

    private function prepareViewData(Letter $letter): array
    {
        $resident = $letter->resident;
        $fieldValues = $letter->fieldValues->keyBy(fn ($fv) => $fv->letterField->field_key);

        // Urutan & label harus sama dengan tabel identitas di template DOCX.
        $fields = [
            ['label' => 'Nama', 'value' => $resident->full_name ?? ''],
            ['label' => 'Jenis Kelamin', 'value' => $this->mapGender($resident->gender ?? '')],
            ['label' => 'Tempat, Tgl. Lahir', 'value' => $this->birthInfo($resident)],
            ['label' => 'Agama', 'value' => $this->titleCase($resident->religion ?? '')],
            ['label' => 'Pekerjaan', 'value' => $resident->occupation ?? ''],
            ['label' => 'Pendidikan Terakhir', 'value' => $resident->last_education ?? ''],
            ['label' => 'Status Perkawinan', 'value' => $this->mapMaritalStatus($resident->marital_status ?? '')],
        ];

        // Garis titik-titik tetap digambar walaupun value terisi, supaya lebar
        // dan alignment kolom identik dengan form aslinya.
        $fields = array_map(fn ($f) => $f + ['dotted' => true], $fields);

        $purpose = $fieldValues->has('keperluan')
            ? ($fieldValues['keperluan']->value ?? '')
            : ($letter->purpose ?? '');

        // Tanggal surat mengikuti waktu approval RT, bukan waktu generate.
        // Locale dipaksa 'id' di sini saja supaya nama bulan berbahasa Indonesia
        // tanpa mengubah APP_LOCALE yang dipakai response API.
        $dateSource = $letter->approved_at ?? now();
        $date = $dateSource->copy()
            ->timezone(config('app.timezone'))
            ->locale('id')
            ->translatedFormat('j F Y');

        // Nama RT yang menandatangani diambil dari approver (user RT yang approve)
        $letter->loadMissing('approvals.approver');
        $approver = $letter->approvals
            ->where('action', 'approved')
            ->sortByDesc('acted_at')
            ->first()?->approver;
        $rtSignatureName = $approver?->name ?? 'GILANG CHOIRUR R.';

        // Signature & stamp menggunakan aset default RT dari config
        $signature = $this->imageDataUriFromConfig(config('letters.default_signature'));
        $stamp = $this->imageDataUriFromConfig(config('letters.default_stamp'));

        return [
            'fields' => $fields,
            'block' => $this->resolveHouseholdBlock($resident),
            'purpose' => $purpose,
            'date' => $date,
            'logo' => $this->imageDataUri(public_path('img/logo-rt.webp')),
            'signature' => $signature,
            'stamp' => $stamp,
            'rt_signature_name' => $rtSignatureName,
        ];
    }

    private function mapGender(string $gender): string
    {
        return match (strtolower($gender)) {
            'male', 'l', 'laki-laki' => 'Laki-laki',
            'female', 'p', 'perempuan' => 'Perempuan',
            default => $gender,
        };
    }

    private function mapMaritalStatus(string $status): string
    {
        return match (strtolower($status)) {
            'single' => 'Belum Menikah',
            'married' => 'Menikah',
            'divorced' => 'Cerai Hidup',
            'widowed' => 'Cerai Mati',
            default => $this->titleCase($status),
        };
    }

    private function titleCase(string $value): string
    {
        return $value === '' ? '' : Str::title($value);
    }

    private function birthInfo(?Resident $resident): string
    {
        if (! $resident) {
            return '';
        }

        $date = $resident->birth_date
            ? $resident->birth_date->copy()->locale('id')->translatedFormat('j F Y')
            : '';

        $place = $resident->birth_place ?? '';

        if ($place !== '' && $date !== '') {
            return $place.', '.$date;
        }

        return $place !== '' ? $place : $date;
    }

    /**
     * Ambil nomor blok dari alamat household.
     *
     * Tabel households hanya punya satu kolom `address` bebas, contoh:
     * "Perum Griya Asri Blok A1 No. 5, RT 005 RW 016" → "A1 No. 5".
     * Kalau pola "Blok" tidak ditemukan, kembalikan string kosong supaya
     * template tidak kemasukan seluruh alamat.
     */
    private function resolveHouseholdBlock(?Resident $resident): string
    {
        if (! $resident) {
            return '';
        }

        $household = $resident->households->first() ?? $resident->headedHousehold;
        $address = $household->address ?? '';

        if ($address === '') {
            return '';
        }

        if (preg_match('/\bblok\s+(.+?)(?=\s*,|\s+RT\b|\s+RW\b|$)/iu', $address, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    /**
     * DOMPDF dijalankan dengan isRemoteEnabled=false, jadi gambar dari private
     * storage dikirim sebagai data URI (bukan URL publik) agar file tetap privat.
     */
    private function imageDataUri(string $path): string
    {
        if ($path === '' || ! is_file($path)) {
            return '';
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };

        if ($mime === null) {
            Log::warning('Unsupported letter image type, skipped', ['path' => $path]);

            return '';
        }

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    private function imageDataUriFromConfig(?string $configPath): string
    {
        if (! $configPath) {
            return '';
        }

        $publicPath = public_path($configPath);
        if (is_file($publicPath)) {
            return $this->imageDataUri($publicPath);
        }

        return '';
    }

    private function generatePdf(string $html): string
    {
        $options = new Options;
        // Semua gambar dikirim sebagai data URI, jadi akses remote tidak diperlukan.
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'times');
        $options->set('defaultPaperSize', 'a5');
        $options->set('defaultPaperOrientation', 'portrait');
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a5', 'portrait');
        $dompdf->render();

        $pdfPath = $this->workDir.'/output.pdf';
        file_put_contents($pdfPath, $dompdf->output());

        return $pdfPath;
    }

    private function validatePdf(string $pdfPath): void
    {
        if (! file_exists($pdfPath)) {
            Log::error('PDF file not created', ['path' => $pdfPath]);

            abort(500, 'Gagal membuat dokumen PDF.');
        }

        $size = filesize($pdfPath);
        if ($size === false || $size === 0) {
            Log::error('PDF file is empty', ['path' => $pdfPath]);

            abort(500, 'Dokumen PDF kosong.');
        }

        $handle = fopen($pdfPath, 'rb');
        $header = fread($handle, 5);
        fclose($handle);

        if (strncmp($header, '%PDF-', 5) !== 0) {
            Log::error('PDF validation failed: invalid header', [
                'path' => $pdfPath,
                'header' => bin2hex($header),
            ]);

            abort(500, 'Dokumen PDF tidak valid.');
        }
    }

    private function storePdf(string $pdfPath, Letter $letter): string
    {
        $filename = $letter->reference_no.'.pdf';
        $dir = config('letters.generated_path').'/'.$letter->id;

        Storage::disk('private')->putFileAs($dir, new File($pdfPath), $filename);

        return $dir.'/'.$filename;
    }

    private function createLetterDocument(Letter $letter, string $storedPath): LetterDocument
    {
        $fileSize = Storage::disk('private')->size($storedPath);

        return LetterDocument::create([
            'letter_id' => $letter->id,
            'document_type' => 'final',
            'path' => $storedPath,
            'file_name' => $letter->reference_no.'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $fileSize,
        ]);
    }

    private function cleanupWorkDir(): void
    {
        $dir = $this->workDir;
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
