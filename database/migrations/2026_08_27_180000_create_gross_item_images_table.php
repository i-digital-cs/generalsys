<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-image gallery for Gross catalog items (stored on local disk).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gross_item_images')) {
            return;
        }

        Schema::create('gross_item_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('gross_items')->cascadeOnDelete();
            $table->string('file_path', 255);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 64)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['item_id', 'sort_order'], 'idx_gross_item_images_item_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gross_item_images');
    }
};
