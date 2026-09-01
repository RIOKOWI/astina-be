<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained('letters');
            $table->foreignId('stamped_by')->constrained('users');
            $table->string('stamp_path');
            $table->timestamp('stamped_at');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stamps');
    }
};
