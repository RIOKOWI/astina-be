<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('letter_type_id')->constrained('letter_types');
            $table->string('field_key');
            $table->string('label');
            $table->enum('field_type', ['text', 'number', 'date', 'textarea', 'select', 'checkbox']);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_fields');
    }
};
