<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $userRt = User::whereHas('roles', fn ($q) => $q->where('code', 'rt'))->first();

        if (! $userRt) {
            return;
        }

        $assets = [
            [
                'name' => 'Kursi Plastik Hitam',
                'category' => 'Furniture',
                'description' => 'Kursi plastik warna hitam untuk keperluan rapat dan acara RT.',
                'quantity' => 50,
                'unit' => 'pcs',
                'purchase_price' => 75000,
                'purchase_date' => '2025-01-15',
                'condition' => 'good',
                'status' => 'available',
            ],
            [
                'name' => 'Meja Lipat Portable',
                'category' => 'Furniture',
                'description' => 'Meja lipat aluminium untuk acara dan pertemuan warga.',
                'quantity' => 10,
                'unit' => 'pcs',
                'purchase_price' => 350000,
                'purchase_date' => '2025-01-15',
                'condition' => 'good',
                'status' => 'available',
            ],
            [
                'name' => 'Sound System Portable',
                'category' => 'Elektronik',
                'description' => 'Speaker aktif + microphone wireless untuk pengumuman dan acara.',
                'quantity' => 1,
                'unit' => 'set',
                'purchase_price' => 2500000,
                'purchase_date' => '2024-06-20',
                'condition' => 'good',
                'status' => 'available',
            ],
            [
                'name' => 'Laptop Lenovo ThinkPad',
                'category' => 'Elektronik',
                'description' => 'Laptop untuk administrasi RT dan pencatatan kegiatan.',
                'quantity' => 1,
                'unit' => 'pcs',
                'purchase_price' => 8500000,
                'purchase_date' => '2024-03-10',
                'condition' => 'good',
                'status' => 'in_use',
            ],
            [
                'name' => 'ATK Lengkap',
                'category' => 'ATK',
                'description' => 'Set lengkap alat tulis kantor: pulpen, pensil, spidol, amplop, map.',
                'quantity' => 5,
                'unit' => 'box',
                'purchase_price' => 150000,
                'purchase_date' => '2025-01-05',
                'condition' => 'good',
                'status' => 'available',
            ],
            [
                'name' => 'Pita Linen + Tiang',
                'category' => 'Peralatan',
                'description' => 'Pita pembatas jalan dan tiang penyangga untuk kegiatan.',
                'quantity' => 4,
                'unit' => 'set',
                'purchase_price' => 200000,
                'purchase_date' => '2025-02-01',
                'condition' => 'good',
                'status' => 'available',
            ],
            [
                'name' => 'Gunting Besi Jalan',
                'category' => 'Peralatan',
                'description' => 'Gunting besar untuk memotong pipa dan keperluan umum.',
                'quantity' => 2,
                'unit' => 'pcs',
                'purchase_price' => 125000,
                'purchase_date' => '2024-08-15',
                'condition' => 'fair',
                'status' => 'available',
            ],
            [
                'name' => 'Pompa Air Listrik',
                'category' => 'Peralatan',
                'description' => 'Pompa air elektrik untuk keperluan irrigation dan banjir.',
                'quantity' => 1,
                'unit' => 'pcs',
                'purchase_price' => 1200000,
                'purchase_date' => '2024-11-20',
                'condition' => 'good',
                'status' => 'available',
            ],
            [
                'name' => 'Senter Darurat LED',
                'category' => 'Keamanan',
                'description' => 'Senter LED rechargeable untuk keadaan darurat dan pemadaman listrik.',
                'quantity' => 6,
                'unit' => 'pcs',
                'purchase_price' => 85000,
                'purchase_date' => '2025-01-10',
                'condition' => 'good',
                'status' => 'available',
            ],
            [
                'name' => 'P3K Box',
                'category' => 'Kebersihan',
                'description' => 'Kotak pertolongan pertama untuk kegiatan.',
                'quantity' => 2,
                'unit' => 'pcs',
                'purchase_price' => 95000,
                'purchase_date' => '2025-01-10',
                'condition' => 'good',
                'status' => 'available',
            ],
            [
                'name' => 'Tenda Kerucut 3x3',
                'category' => 'Furniture',
                'description' => 'Tenda untuk acara warga di lapangan.',
                'quantity' => 3,
                'unit' => 'pcs',
                'purchase_price' => 600000,
                'purchase_date' => '2025-03-01',
                'condition' => 'good',
                'status' => 'available',
            ],
            [
                'name' => 'Pengganjal Pintu',
                'category' => 'Peralatan',
                'description' => 'Wedge pintu untuk kegiatan.',
                'quantity' => 8,
                'unit' => 'pcs',
                'purchase_price' => 15000,
                'purchase_date' => '2024-12-05',
                'condition' => 'fair',
                'status' => 'available',
            ],
        ];

        $lastAsset = Asset::query()->lockForUpdate()->orderByDesc('id')->first();
        $startNumber = $lastAsset ? ((int) substr($lastAsset->code, 4) + 1) : 1;

        foreach ($assets as $i => $data) {
            $code = 'AST-'.str_pad((string) ($startNumber + $i), 6, '0', STR_PAD_LEFT);

            $asset = Asset::create(array_merge($data, ['code' => $code]));

            if ($data['quantity'] > 0) {
                AssetMovement::create([
                    'asset_id' => $asset->id,
                    'created_by' => $userRt->id,
                    'type' => 'in',
                    'quantity' => $data['quantity'],
                    'description' => 'Stok awal',
                    'movement_at' => $data['purchase_date'],
                    'created_at' => now(),
                ]);
            }
        }
    }
}
