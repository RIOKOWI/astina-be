<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained('residents');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->string('reference_no')->unique();
            $table->string('title');
            $table->text('description');
            $table->enum('category', ['facility', 'security', 'cleanliness', 'noise', 'dispute', 'other']);
            $table->enum('status', ['submitted', 'approved', 'rejected', 'in_progress', 'resolved'])->default('submitted');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
