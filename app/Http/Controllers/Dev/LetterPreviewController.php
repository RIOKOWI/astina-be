<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LetterPreviewController extends Controller
{
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
            return '';
        }

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }

    public function show(Request $request)
    {
        $fields = [
            ['label' => 'Nama', 'value' => 'Budi Santoso'],
            ['label' => 'Jenis Kelamin', 'value' => 'Laki-laki'],
            ['label' => 'Tempat, Tgl. Lahir', 'value' => 'Herminfort, 20 Oktober 2003'],
            ['label' => 'Agama', 'value' => 'Kristen'],
            ['label' => 'Pekerjaan', 'value' => 'Wiraswasta'],
            ['label' => 'Pendidikan Terakhir', 'value' => 'S2'],
            ['label' => 'Status Perkawinan', 'value' => 'Cerai Hidup'],
        ];

        return response()->make(
            view('letters.surat-pengantar', [
                'fields' => $fields,
                'block' => 'A1 No. 5',
                'purpose' => 'Membuat KTP baru',
                'date' => '10 September 2026',
                'logo' => $this->imageDataUri(public_path('img/logo-rt.webp')),
                'signature' => $this->imageDataUri(public_path('sign/tanda-tangan.png')),
                'stamp' => $this->imageDataUri(public_path('stamp/stempel.png')),
                'rt_signature_name' => 'GILANG CHOIRUR R.',
            ])->render(),
            200,
            ['Content-Type' => 'text/html']
        );
    }

    public function reference()
    {
        $path = base_path('docs/Letter/surat-pengantar.png');

        if (! file_exists($path)) {
            abort(404, 'Reference image not found.');
        }

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function compare()
    {
        return view('dev.letters.surat-pengantar-compare');
    }
}
