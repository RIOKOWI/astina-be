<?php

namespace Database\Seeders;

use App\Models\Due;
use App\Models\DueBill;
use App\Models\FinancialTransaction;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $userRt = User::whereHas('roles', fn ($q) => $q->where('code', 'rt'))->first();
        $userBendahara = User::whereHas('roles', fn ($q) => $q->where('code', 'bendahara'))->first();
        $residents = Resident::where('status', 'active')->get();

        if ($residents->isEmpty() || ! $userRt || ! $userBendahara) {
            return;
        }

        $iuranBulanan = Due::updateOrCreate(
            ['name' => 'Iuran Bulanan RT 005'],
            [
                'description' => 'Iuran bulanan wajib untuk kas RT 005',
                'amount' => 50000,
                'frequency' => 'monthly',
                'start_date' => now()->startOfYear(),
                'end_date' => null,
                'is_active' => true,
            ],
        );

        $iuranSatuan = Due::updateOrCreate(
            ['name' => 'Donasi Kegiatan RT'],
            [
                'description' => 'Donasi sukarela untuk kegiatan RT',
                'amount' => 100000,
                'frequency' => 'one_time',
                'start_date' => now()->startOfYear(),
                'end_date' => null,
                'is_active' => true,
            ],
        );

        $iuranInaktif = Due::updateOrCreate(
            ['name' => 'Iuran Khusus Covid-19'],
            [
                'description' => 'Iuran untuk kegiatan penanganan Covid-19',
                'amount' => 25000,
                'frequency' => 'monthly',
                'start_date' => '2020-04-01',
                'end_date' => '2022-12-31',
                'is_active' => false,
            ],
        );

        // DueBills untuk 3 bulan terakhir
        foreach ([now()->subMonths(2), now()->subMonth(), now()] as $month) {
            $year = $month->year;
            $monthNum = $month->month;
            $dueDate = sprintf('%04d-%02d-05', $year, $monthNum);

            foreach ($residents as $index => $resident) {
                $isPaid = fake()->boolean(60);
                $status = $isPaid ? 'paid' : 'unpaid';

                DueBill::updateOrCreate(
                    [
                        'due_id' => $iuranBulanan->id,
                        'resident_id' => $resident->id,
                        'due_date' => $dueDate,
                    ],
                    [
                        'amount' => $iuranBulanan->amount,
                        'status' => $status,
                    ],
                );
            }
        }

        // Financial transactions untuk history kas
        $transactions = [
            [
                'type' => 'income',
                'amount' => 1000000,
                'category' => 'donasi',
                'description' => 'Donasi Opening Ceremony RT 005 dari warga',
                'transaction_at' => now()->subMonths(6),
            ],
            [
                'type' => 'income',
                'amount' => 1500000,
                'category' => 'iuran',
                'description' => 'Iuran bulanan warga bulan lalu',
                'transaction_at' => now()->subMonths(1)->startOfMonth()->addDays(5),
            ],
            [
                'type' => 'income',
                'amount' => 1500000,
                'category' => 'iuran',
                'description' => 'Iuran bulanan warga bulan ini',
                'transaction_at' => now()->startOfMonth()->addDays(5),
            ],
            [
                'type' => 'expense',
                'amount' => 500000,
                'category' => 'operational',
                'description' => 'Pembelian perlengkapan kantor RT',
                'transaction_at' => now()->subMonths(2),
            ],
            [
                'type' => 'expense',
                'amount' => 300000,
                'category' => 'kebersihan',
                'description' => 'Gaji petugas kebersihan bulan lalu',
                'transaction_at' => now()->subMonths(1)->startOfMonth()->addDays(10),
            ],
            [
                'type' => 'expense',
                'amount' => 300000,
                'category' => 'kebersihan',
                'description' => 'Gaji petugas kebersihan bulan ini',
                'transaction_at' => now()->startOfMonth()->addDays(10),
            ],
            [
                'type' => 'expense',
                'amount' => 750000,
                'category' => 'perlengkapan',
                'description' => 'Pembelian kursi plastik 10 pcs untuk rapat RT',
                'transaction_at' => now()->subDays(15),
            ],
            [
                'type' => 'expense',
                'amount' => 200000,
                'category' => 'operational',
                'description' => 'Biaya ATK dan print fotokopi',
                'transaction_at' => now()->subDays(7),
            ],
            [
                'type' => 'expense',
                'amount' => 100000,
                'category' => 'keamanan',
                'description' => 'Pemasangan gembok baru untuk pos security',
                'transaction_at' => now()->subDays(3),
            ],
        ];

        foreach ($transactions as $txData) {
            FinancialTransaction::create(array_merge($txData, [
                'created_by' => $userRt->id,
                'payment_id' => null,
            ]));
        }

        // Sample payments dengan status berbeda
        $sampleStatuses = [
            ['status' => 'pending', 'has_proof' => true],
            ['status' => 'pending', 'has_proof' => true],
            ['status' => 'approved', 'has_proof' => true],
            ['status' => 'approved', 'has_proof' => true],
            ['status' => 'rejected', 'has_proof' => false],
        ];

        foreach (array_slice($residents->toArray(), 0, 5) as $i => $residentData) {
            $resident = Resident::find($residentData['id']);
            $dueBill = DueBill::where('resident_id', $resident->id)
                ->where('due_id', $iuranBulanan->id)
                ->whereMonth('due_date', now()->subMonth()->month)
                ->first();

            if (! $dueBill) {
                continue;
            }

            $sample = $sampleStatuses[$i];
            $payment = Payment::updateOrCreate(
                [
                    'due_bill_id' => $dueBill->id,
                    'resident_id' => $resident->id,
                ],
                [
                    'amount' => $iuranBulanan->amount,
                    'method' => fake()->randomElement(['transfer', 'cash', 'ewallet', 'other']),
                    'status' => $sample['status'],
                    'paid_at' => now()->subDays(fake()->numberBetween(1, 10)),
                    'approved_at' => $sample['status'] === 'approved' ? now()->subDays(fake()->numberBetween(1, 5)) : null,
                    'approved_by' => $sample['status'] === 'approved' ? $userBendahara->id : null,
                    'rejection_reason' => $sample['status'] === 'rejected' ? 'Bukti transfer tidak jelas, mohon upload ulang.' : null,
                ],
            );

            if ($sample['has_proof']) {
                PaymentProof::firstOrCreate(
                    ['payment_id' => $payment->id],
                    [
                        'path' => 'payment-proofs/'.$payment->id.'/sample-'.fake()->uuid().'.jpg',
                        'file_name' => 'bukti-'.fake()->word().'.jpg',
                        'mime_type' => 'image/jpeg',
                        'file_size' => fake()->numberBetween(100000, 1000000),
                        'created_at' => now(),
                    ],
                );
            }
        }
    }
}
