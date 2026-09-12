<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_whatsapp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_warga');
            $table->string('id_message', 30);
            $table->string('message', 4000);
            $table->timestamp('incoming_time');
            $table->text('replied')->nullable();

            $table->foreign('id_warga')
                ->references('id')
                ->on('residents')
                ->restrictOnDelete();

            $table->unique('id_message');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_whatsapp');
    }
};
