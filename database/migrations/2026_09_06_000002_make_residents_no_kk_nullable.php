<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE residents MODIFY COLUMN no_kk VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE residents MODIFY COLUMN no_kk VARCHAR(255) NOT NULL');
    }
};
