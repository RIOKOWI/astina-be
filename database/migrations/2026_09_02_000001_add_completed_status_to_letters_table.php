<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE letters MODIFY COLUMN status ENUM('draft', 'submitted', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE letters MODIFY COLUMN status ENUM('draft', 'submitted', 'approved', 'rejected') NOT NULL DEFAULT 'draft'");
    }
};
