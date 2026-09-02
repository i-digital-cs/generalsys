<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gross_treasury_voucher_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('gross_treasury_vouchers')->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('file_path', 255);
            $table->string('mime_type', 120);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('voucher_id', 'idx_gross_treas_att_voucher');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gross_treasury_voucher_attachments');
    }
};
