<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change enum to string for more flexibility
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('status', 20)->default('submitted')->change();
        });

        // Map old 'approved' status to 'reviewed'
        DB::table('complaints')->where('status', 'approved')->update(['status' => 'reviewed']);
    }

    public function down(): void
    {
        // Map 'reviewed' back to 'approved'
        DB::table('complaints')->where('status', 'reviewed')->update(['status' => 'approved']);

        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('status', ['submitted', 'approved', 'rejected', 'in_progress', 'resolved'])->default('submitted')->change();
        });
    }
};
