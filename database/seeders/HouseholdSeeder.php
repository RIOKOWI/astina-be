<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HouseholdSeeder extends Seeder
{
    public function run(): void
    {
        $household1 = Household::factory()->create([
            'no_kk' => '3271234567890001',
            'head_resident_id' => null,
            'address' => 'Perum Griya Asri Blok A1 No. 5, RT 005 RW 016',
            'rt' => '005',
            'rw' => '016',
            'postal_code' => '15111',
            'status' => 'active',
        ]);

        $residentRt = Resident::firstOrCreate(
            ['phone' => '081211111111'],
            [
                'nik' => '3271234567890001',
                'no_kk' => '3271234567890001',
                'full_name' => 'H. Ahmad Wijaya',
                'birth_place' => 'Jakarta',
                'birth_date' => '1980-05-15',
                'gender' => 'male',
                'religion' => 'islam',
                'marital_status' => 'married',
                'occupation' => 'PNS',
                'email' => 'ahmad.rt05@astina.local',
                'status' => 'active',
                'joined_at' => now(),
            ],
        );

        $residentBendahara = Resident::firstOrCreate(
            ['phone' => '081222222222'],
            [
                'nik' => '3271234567890002',
                'no_kk' => '3271234567890001',
                'full_name' => 'Siti Nurhaliza',
                'birth_place' => 'Tangerang',
                'birth_date' => '1985-08-20',
                'gender' => 'female',
                'religion' => 'islam',
                'marital_status' => 'married',
                'occupation' => 'Ibu Rumah Tangga',
                'email' => 'siti.bendahara@astina.local',
                'status' => 'active',
                'joined_at' => now(),
            ],
        );

        $residentWarga = Resident::firstOrCreate(
            ['phone' => '081233333333'],
            [
                'nik' => '3271234567890003',
                'no_kk' => '3271234567890001',
                'full_name' => 'Budi Santoso',
                'birth_place' => 'Bandung',
                'birth_date' => '1990-03-10',
                'gender' => 'male',
                'religion' => 'islam',
                'marital_status' => 'single',
                'occupation' => 'Software Engineer',
                'email' => 'budi.warga@astina.local',
                'status' => 'active',
                'joined_at' => now(),
            ],
        );

        $household1->update(['head_resident_id' => $residentRt->id]);

        $residentRt->households()->attach($household1, [
            'relationship' => 'head',
            'joined_at' => now(),
            'is_current' => true,
        ]);
        $residentBendahara->households()->attach($household1, [
            'relationship' => 'spouse',
            'joined_at' => now(),
            'is_current' => true,
        ]);
        $residentWarga->households()->attach($household1, [
            'relationship' => 'child',
            'joined_at' => now(),
            'is_current' => true,
        ]);

        $household2 = Household::factory()->create([
            'no_kk' => '3271234567890002',
            'head_resident_id' => null,
            'address' => 'Perum Griya Asri Blok B2 No. 8, RT 005 RW 016',
            'rt' => '005',
            'rw' => '016',
            'postal_code' => '15111',
            'status' => 'active',
        ]);

        $residentDewi = Resident::factory()->create([
            'nik' => '3271234567890004',
            'no_kk' => '3271234567890002',
            'full_name' => 'Dewi Lestari',
            'birth_place' => 'Semarang',
            'birth_date' => '1988-12-05',
            'gender' => 'female',
            'religion' => 'islam',
            'marital_status' => 'married',
            'occupation' => 'Guru',
            'phone' => '081244444444',
            'email' => 'dewi.lestari@astina.local',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $residentAndi = Resident::factory()->create([
            'nik' => '3271234567890005',
            'no_kk' => '3271234567890002',
            'full_name' => 'Andi Pratama',
            'birth_place' => 'Surabaya',
            'birth_date' => '1992-07-22',
            'gender' => 'male',
            'religion' => 'islam',
            'marital_status' => 'single',
            'occupation' => 'Wiraswasta',
            'phone' => '081255555555',
            'email' => 'andi.pratama@astina.local',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $household2->update(['head_resident_id' => $residentDewi->id]);

        $residentDewi->households()->attach($household2, [
            'relationship' => 'head',
            'joined_at' => now(),
            'is_current' => true,
        ]);
        $residentAndi->households()->attach($household2, [
            'relationship' => 'child',
            'joined_at' => now(),
            'is_current' => true,
        ]);

        $household3 = Household::factory()->create([
            'no_kk' => '3271234567890003',
            'head_resident_id' => null,
            'address' => 'Perum Griya Asri Blok C3 No. 12, RT 006 RW 016',
            'rt' => '006',
            'rw' => '016',
            'postal_code' => '15111',
            'status' => 'active',
        ]);

        $residentHendra = Resident::factory()->create([
            'nik' => '3271234567890006',
            'no_kk' => '3271234567890003',
            'full_name' => 'Hendra Wijaya',
            'birth_place' => 'Yogyakarta',
            'birth_date' => '1975-02-14',
            'gender' => 'male',
            'religion' => 'kristen',
            'marital_status' => 'married',
            'occupation' => 'PNS',
            'phone' => '081266666666',
            'email' => 'hendra.wijaya@astina.local',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $household3->update(['head_resident_id' => $residentHendra->id]);

        $residentHendra->households()->attach($household3, [
            'relationship' => 'head',
            'joined_at' => now(),
            'is_current' => true,
        ]);

        $roleRt = Role::firstOrCreate(['code' => 'rt'], ['name' => 'RT']);
        $roleBendahara = Role::firstOrCreate(['code' => 'bendahara'], ['name' => 'Bendahara']);
        $roleWarga = Role::firstOrCreate(['code' => 'warga'], ['name' => 'Warga']);

        User::firstOrCreate(
            ['phone' => '081211111111'],
            [
                'resident_id' => $residentRt->id,
                'email' => 'ahmad.rt05@astina.local',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
        )->roles()->syncWithoutDetaching([$roleRt->id]);

        User::firstOrCreate(
            ['phone' => '081222222222'],
            [
                'resident_id' => $residentBendahara->id,
                'email' => 'siti.bendahara@astina.local',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
        )->roles()->syncWithoutDetaching([$roleBendahara->id]);

        User::firstOrCreate(
            ['phone' => '081233333333'],
            [
                'resident_id' => $residentWarga->id,
                'email' => 'budi.warga@astina.local',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ],
        )->roles()->syncWithoutDetaching([$roleWarga->id]);
    }
}
