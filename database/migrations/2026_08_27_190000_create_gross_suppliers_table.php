<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gross_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('phone', 40);
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('phone', 'idx_gross_supp_phone');
            $table->index('name', 'idx_gross_supp_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gross_suppliers');
    }
};
