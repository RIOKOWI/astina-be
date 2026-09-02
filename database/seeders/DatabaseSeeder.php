<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AuthTestSeeder::class,
            HouseholdSeeder::class,
            AssetSeeder::class,
            ComplaintSeeder::class,
            FinanceSeeder::class,
            LetterSeeder::class,
        ]);
    }
}
