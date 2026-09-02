<?php

namespace Database\Seeders;

use App\Models\LetterField;
use App\Models\LetterType;
use Illuminate\Database\Seeder;

class LetterSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'SKD',
                'name' => 'Surat Keterangan Domisili',
                'description' => 'Surat keterangan domisili untuk warga RT 005',
                'fields' => [
                    ['field_key' => 'keperluan', 'label' => 'Keperluan', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 1],
                    ['field_key' => 'jumlah_anggota', 'label' => 'Jumlah Anggota Keluarga', 'field_type' => 'number', 'is_required' => false, 'sort_order' => 2],
                ],
            ],
            [
                'code' => 'SKP',
                'name' => 'Surat Keterangan Pindah',
                'description' => 'Surat keterangan pindah untuk warga yang akan pindah domisili',
                'fields' => [
                    ['field_key' => 'alamat_tujuan', 'label' => 'Alamat Tujuan', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 1],
                    ['field_key' => 'alasan_pindah', 'label' => 'Alasan Pindah', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 2],
                    ['field_key' => 'tanggal_pindah', 'label' => 'Tanggal Pindah', 'field_type' => 'date', 'is_required' => true, 'sort_order' => 3],
                    ['field_key' => 'anggota_ikut_pindah', 'label' => 'Anggota Keluarga yang Ikut Pindah', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 4],
                ],
            ],
            [
                'code' => 'SKU',
                'name' => 'Surat Keterangan Usaha',
                'description' => 'Surat keterangan usaha untuk warga yang memiliki usaha di wilayah RT 005',
                'fields' => [
                    ['field_key' => 'nama_usaha', 'label' => 'Nama Usaha', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                    ['field_key' => 'jenis_usaha', 'label' => 'Jenis Usaha', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 2],
                    ['field_key' => 'alamat_usaha', 'label' => 'Alamat Usaha', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 3],
                    ['field_key' => 'keperluan', 'label' => 'Keperluan', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 4],
                ],
            ],
            [
                'code' => 'SKM',
                'name' => 'Surat Keterangan Tidak Mampu',
                'description' => 'Surat keterangan tidak mampu untuk keperluan pengurusan bantuan sosial',
                'fields' => [
                    ['field_key' => 'keperluan', 'label' => 'Keperluan', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 1],
                    ['field_key' => 'jumlah_tanggungan', 'label' => 'Jumlah Tanggungan', 'field_type' => 'number', 'is_required' => true, 'sort_order' => 2],
                    ['field_key' => 'penghasilan_per_bulan', 'label' => 'Penghasilan Per Bulan', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 3],
                ],
            ],
            [
                'code' => 'SKT',
                'name' => 'Surat Keterangan Kelahiran',
                'description' => 'Surat keterangan kelahiran untuk bayi yang lahir di wilayah RT 005',
                'fields' => [
                    ['field_key' => 'nama_bayi', 'label' => 'Nama Bayi', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                    ['field_key' => 'tanggal_lahir', 'label' => 'Tanggal Lahir', 'field_type' => 'date', 'is_required' => true, 'sort_order' => 2],
                    ['field_key' => 'tempat_lahir', 'label' => 'Tempat Lahir', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 3],
                    ['field_key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'field_type' => 'select', 'is_required' => true, 'sort_order' => 4],
                    ['field_key' => 'nama_orang_tua', 'label' => 'Nama Orang Tua', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 5],
                ],
            ],
            [
                'code' => 'SKK',
                'name' => 'Surat Keterangan Kematian',
                'description' => 'Surat keterangan kematian untuk warga RT 005',
                'fields' => [
                    ['field_key' => 'nama_almarhum', 'label' => 'Nama Almarhum/Almarhumah', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 1],
                    ['field_key' => 'nik_almarhum', 'label' => 'NIK Almarhum', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 2],
                    ['field_key' => 'tanggal_kematian', 'label' => 'Tanggal Kematian', 'field_type' => 'date', 'is_required' => true, 'sort_order' => 3],
                    ['field_key' => 'tempat_kematian', 'label' => 'Tempat Kematian', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 4],
                    ['field_key' => 'penyebab_kematian', 'label' => 'Penyebab Kematian', 'field_type' => 'textarea', 'is_required' => false, 'sort_order' => 5],
                ],
            ],
        ];

        foreach ($types as $typeData) {
            $fields = $typeData['fields'];
            unset($typeData['fields']);

            $letterType = LetterType::updateOrCreate(
                ['code' => $typeData['code']],
                array_merge($typeData, ['is_active' => true]),
            );

            foreach ($fields as $fieldData) {
                LetterField::updateOrCreate(
                    [
                        'letter_type_id' => $letterType->id,
                        'field_key' => $fieldData['field_key'],
                    ],
                    $fieldData,
                );
            }
        }
    }
}
