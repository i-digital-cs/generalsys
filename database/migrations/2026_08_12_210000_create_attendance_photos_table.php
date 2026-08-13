<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-6.6 — Optional multi photos attached at clock-in / clock-out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->enum('kind', ['CLOCK_IN', 'CLOCK_OUT']);
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['attendance_id', 'kind'], 'idx_att_photos_att_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_photos');
    }
};
