<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_id')->constrained('letters');
            $table->foreignId('letter_field_id')->constrained('letter_fields');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_field_values');
    }
};
