<?php

namespace Database\Seeders;

use App\Models\Resident;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuthTestSeeder extends Seeder
{
    public function run(): void
    {
        $roleRt = Role::firstOrCreate(['code' => 'rt'], ['name' => 'RT']);
        $roleBendahara = Role::firstOrCreate(['code' => 'bendahara'], ['name' => 'Bendahara']);
        $roleWarga = Role::firstOrCreate(['code' => 'warga'], ['name' => 'Warga']);

        $residentRt = Resident::factory()->create(['full_name' => 'H. Ahmad Wijaya', 'phone' => '081211111111']);
        $residentBendahara = Resident::factory()->create(['full_name' => 'Siti Nurhaliza', 'phone' => '081222222222']);
        $residentWarga = Resident::factory()->create(['full_name' => 'Budi Santoso', 'phone' => '081233333333']);
        $residentWarga2 = Resident::factory()->create(['full_name' => 'Dewi Lestari', 'phone' => '081244444444']);

        $userRt = User::factory()->create([
            'resident_id' => $residentRt->id,
            'phone' => '081211111111',
            'email' => 'ahmad.rt05@astina.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $userRt->roles()->attach($roleRt);

        $userBendahara = User::factory()->create([
            'resident_id' => $residentBendahara->id,
            'phone' => '081222222222',
            'email' => 'siti.bendahara@astina.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $userBendahara->roles()->attach($roleBendahara);

        $userWarga = User::factory()->create([
            'resident_id' => $residentWarga->id,
            'phone' => '081233333333',
            'email' => 'budi.warga@astina.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $userWarga->roles()->attach($roleWarga);

        $userWarga2 = User::factory()->create([
            'resident_id' => $residentWarga2->id,
            'phone' => '081244444444',
            'email' => 'dewi.warga@astina.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $userWarga2->roles()->attach($roleWarga);
    }
}
