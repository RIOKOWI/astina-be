<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ComplaintSeeder extends Seeder
{
    public function run(): void
    {
        $userRt = User::whereHas('roles', fn ($q) => $q->where('code', 'rt'))->first();
        $residents = Resident::where('status', 'active')->get();

        if ($residents->isEmpty() || ! $userRt) {
            return;
        }

        $complaints = [
            [
                'resident' => $residents[0],
                'title' => 'Lampu jalan mati di blok A',
                'description' => 'Sudah 3 hari lampu jalan di depan blok A No. 5 mati. Mengganggu keamanan di malam hari.',
                'category' => 'facility',
                'status' => 'submitted',
                'submitted_at' => now()->subDays(1),
            ],
            [
                'resident' => $residents->count() > 1 ? $residents[1] : $residents[0],
                'title' => 'Banjir di gang kecil blok B',
                'description' => 'Setiap hujan deras, gang kecil di blok B selalu tergenang air. Perlu perhatian untuk saluran air.',
                'category' => 'cleanliness',
                'status' => 'submitted',
                'submitted_at' => now()->subDays(2),
            ],
            [
                'resident' => $residents->count() > 2 ? $residents[2] : $residents[0],
                'title' => 'Keributan warga larut malam',
                'description' => 'Ada keributan dan suara bising dari blok C pada hari Sabtu malam hingga jam 2 pagi.',
                'category' => 'security',
                'status' => 'reviewed',
                'submitted_at' => now()->subDays(5),
                'reviewed_at' => now()->subDays(4),
                'assigned_to' => $userRt->id,
            ],
            [
                'resident' => $residents->count() > 3 ? $residents[3] : $residents[0],
                'title' => 'Tong sampah di depan rumah penuh',
                'description' => 'Tempat sampah di depan blok A sudah penuh dan tidak ada jemputan sampah selama 1 minggu.',
                'category' => 'cleanliness',
                'status' => 'in_progress',
                'submitted_at' => now()->subDays(7),
                'reviewed_at' => now()->subDays(6),
                'resolved_at' => now()->subDays(2),
                'assigned_to' => $userRt->id,
            ],
            [
                'resident' => $residents->count() > 4 ? $residents[4] : $residents[0],
                'title' => 'Talang air rumah warga bocor',
                'description' => 'Talang air di rumah No. 12 blok B banyak yang bocor dan airnya meresap ke tembok tetangga.',
                'category' => 'facility',
                'status' => 'resolved',
                'submitted_at' => now()->subDays(14),
                'reviewed_at' => now()->subDays(13),
                'resolved_at' => now()->subDays(5),
                'assigned_to' => $userRt->id,
            ],
            [
                'resident' => $residents->count() > 5 ? $residents[5] : $residents[0],
                'title' => 'Parkir liar di depan gerbang RT',
                'description' => 'Kendaraan yang tidak dikenal sering parkir sembarangan di depan gerbang RT, menghalangi akses.',
                'category' => 'security',
                'status' => 'closed',
                'submitted_at' => now()->subDays(20),
                'reviewed_at' => now()->subDays(19),
                'resolved_at' => now()->subDays(10),
                'closed_at' => now()->subDays(3),
                'assigned_to' => $userRt->id,
            ],
            [
                'resident' => $residents->count() > 6 ? $residents[6] : $residents[0],
                'title' => 'Jalan rusak di blok D',
                'description' => 'Banyak lubang di jalan blok D yang sudah membahayakan pejalan kaki dan pengguna sepeda.',
                'category' => 'facility',
                'status' => 'rejected',
                'submitted_at' => now()->subDays(30),
                'rejection_reason' => 'Perbaikan jalan rusak merupakan tanggung jawab pemerintah Kelurahan, bukan RT. Silakan hubungi Kelurahan terkait.',
            ],
        ];

        $lastComplaint = Complaint::orderByDesc('id')->first();
        $startNumber = $lastComplaint ? ((int) Str::afterLast($lastComplaint->reference_no, '-') + 1) : 1;

        foreach ($complaints as $i => $data) {
            $resident = $data['resident'];
            unset($data['resident']);

            $refNumber = 'CMP-'.date('Y').'-'.str_pad((string) ($startNumber + $i), 6, '0', STR_PAD_LEFT);

            Complaint::create(array_merge($data, [
                'resident_id' => $resident->id,
                'reference_no' => $refNumber,
            ]));
        }
    }
}
