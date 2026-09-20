<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gross_sales_invoice_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained('gross_sales_invoices')->cascadeOnDelete();
            $table->string('title', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('original_name', 255);
            $table->string('file_path', 255);
            $table->string('mime_type', 120);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('sales_invoice_id', 'idx_gross_sales_media_invoice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gross_sales_invoice_media');
    }
};
