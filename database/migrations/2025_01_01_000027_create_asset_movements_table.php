<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignId('created_by')->constrained('users');
            $table->enum('type', ['in', 'out', 'adjustment']);
            $table->integer('quantity');
            $table->text('description')->nullable();
            $table->timestamp('movement_at');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
    }
};
