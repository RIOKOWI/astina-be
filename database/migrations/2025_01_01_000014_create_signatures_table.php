<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained('letters');
            $table->foreignId('signed_by')->constrained('users');
            $table->string('signature_path');
            $table->string('signature_hash');
            $table->timestamp('signed_at');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};
