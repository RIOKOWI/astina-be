<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('due_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('due_id')->constrained('dues');
            $table->foreignId('resident_id')->constrained('residents');
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->enum('status', ['unpaid', 'paid', 'overdue'])->default('unpaid');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('due_bills');
    }
};
