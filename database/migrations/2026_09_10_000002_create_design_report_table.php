<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_report', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->text('content');
            $table->text('header');
            $table->text('footer');
            $table->string('rw_approval', 1)->default('N');
            $table->string('lurah_approval', 1)->default('N');
            $table->string('camat_approval', 1)->default('N');
            $table->timestamp('cdt')->useCurrent();
            $table->timestamp('mdt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_report');
    }
};
