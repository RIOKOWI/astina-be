<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->renameColumn('approved_at', 'reviewed_at');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->renameColumn('reviewed_at', 'approved_at');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
