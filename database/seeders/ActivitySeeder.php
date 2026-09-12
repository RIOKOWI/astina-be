<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $rtUsers = User::whereHas('roles', fn ($q) => $q->where('code', 'rt'))->get();
        $createdBy = $rtUsers->isNotEmpty() ? $rtUsers->first()->id : User::factory()->create()->id;

        $activities = [
            [
                'title' => 'Gotong Royong Bulanan',
                'description' => 'Kegiatan kerja bakti bulanan untuk membersihkan lingkungan RT 005. Dimulai dari pos ronda hingga jalan lingkungan.',
                'location' => 'Seluruh area RT 005 RW 016',
                'start_at' => now()->addDays(7)->setTime(7, 0),
                'end_at' => now()->addDays(7)->setTime(10, 0),
                'status' => 'published',
            ],
            [
                'title' => 'Rapat Bulanan RT',
                'description' => 'Pembahasan program kerja RT, evaluasi iuran bulanan, dan evaluasi kegiatan bulan sebelumnya.',
                'location' => 'Balai RT 005',
                'start_at' => now()->addDays(14)->setTime(19, 30),
                'end_at' => now()->addDays(14)->setTime(21, 0),
                'status' => 'published',
            ],
            [
                'title' => 'Posyandu Balita',
                'description' => 'Pemeriksaan kesehatan dan penimbangan balita di wilayah RT 005. Dilaksanakan setiap bulan.',
                'location' => 'Pos Kesehatan RT 005',
                'start_at' => now()->addDays(21)->setTime(9, 0),
                'end_at' => now()->addDays(21)->setTime(12, 0),
                'status' => 'published',
            ],
            [
                'title' => 'Kerja Bakti Khusus',
                'description' => 'Pembersihan saluran air dan perbaikan jalan lingkungan yang rusak. Diperlukan partisipasi seluruh warga.',
                'location' => 'Jalan lingkungan RT 005 blok B',
                'start_at' => now()->addDays(10)->setTime(6, 30),
                'end_at' => now()->addDays(10)->setTime(11, 0),
                'status' => 'published',
            ],
            [
                'title' => 'SosialisasiProgram',
                'description' => 'Sosialisasi program kerja RT semester II dan penjelasan penggunaan aplikasi ASTINA untuk warga.',
                'location' => 'Balai RT 005',
                'start_at' => now()->addDays(28)->setTime(19, 0),
                'end_at' => now()->addDays(28)->setTime(20, 30),
                'status' => 'published',
            ],
            [
                'title' => 'Rapat Persiapan Hari Raya',
                'description' => 'Koordinasi persiapan menjelang hari raya. Pembagian tugas dan jadwal ronda.',
                'location' => 'Balai RT 005',
                'start_at' => now()->addDays(5)->setTime(20, 0),
                'end_at' => now()->addDays(5)->setTime(21, 0),
                'status' => 'published',
            ],
            [
                'title' => 'Rapat Luar Biasa',
                'description' => 'Pembahasan isu lingkungan dan keamanan di wil RT 005.',
                'location' => 'Balai RT 005',
                'start_at' => now()->addDays(3)->setTime(19, 0),
                'end_at' => now()->addDays(3)->setTime(20, 30),
                'status' => 'draft',
            ],
            [
                'title' => 'Gotong Royong bulan lalu',
                'description' => 'Kegiatan kerja bakti bulan kemarin.',
                'location' => 'Seluruh area RT 005 RW 016',
                'start_at' => now()->subDays(30)->setTime(7, 0),
                'end_at' => now()->subDays(30)->setTime(10, 0),
                'status' => 'completed',
            ],
        ];

        foreach ($activities as $activity) {
            Activity::create(array_merge($activity, ['created_by' => $createdBy]));
        }
    }
}
