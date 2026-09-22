<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gross_receipt_purposes', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name_en', 'uq_gross_receipt_purpose_en');
        });

        Schema::table('gross_treasury_vouchers', function (Blueprint $table) {
            $table->foreignId('receipt_purpose_id')
                ->nullable()
                ->after('expense_type_id')
                ->constrained('gross_receipt_purposes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gross_treasury_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_purpose_id');
        });

        Schema::dropIfExists('gross_receipt_purposes');
    }
};
