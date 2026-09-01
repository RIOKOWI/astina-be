<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('due_bill_id')->constrained('due_bills');
            $table->foreignId('resident_id')->constrained('residents');
            $table->decimal('amount', 15, 2);
            $table->enum('method', ['cash', 'transfer', 'ewallet', 'other'])->default('transfer');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
