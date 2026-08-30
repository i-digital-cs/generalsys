<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed color / size / model lists for Gross items (no free-text duplicates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gross_item_attributes', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['COLOR', 'SIZE', 'MODEL']);
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->timestamps();
            $table->unique(['kind', 'name_en'], 'uq_gross_attr_kind_en');
            $table->index('kind', 'idx_gross_attr_kind');
        });

        Schema::table('gross_items', function (Blueprint $table) {
            $table->foreignId('color_id')->nullable()->after('name')
                ->constrained('gross_item_attributes')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->after('color_id')
                ->constrained('gross_item_attributes')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->after('size_id')
                ->constrained('gross_item_attributes')->nullOnDelete();
        });

        $this->backfillFromLegacyStrings();
    }

    public function down(): void
    {
        Schema::table('gross_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('color_id');
            $table->dropConstrainedForeignId('size_id');
            $table->dropConstrainedForeignId('model_id');
        });
        Schema::dropIfExists('gross_item_attributes');
    }

    private function backfillFromLegacyStrings(): void
    {
        $map = [
            'color' => 'COLOR',
            'size' => 'SIZE',
            'model' => 'MODEL',
        ];

        foreach ($map as $column => $kind) {
            $values = DB::table('gross_items')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->distinct()
                ->pluck($column);

            foreach ($values as $value) {
                $name = trim((string) $value);
                if ($name === '') {
                    continue;
                }

                $existingId = DB::table('gross_item_attributes')
                    ->where('kind', $kind)
                    ->where('name_en', $name)
                    ->value('id');

                if ($existingId === null) {
                    $existingId = DB::table('gross_item_attributes')->insertGetId([
                        'kind' => $kind,
                        'name_ar' => $name,
                        'name_en' => $name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $fk = $column.'_id';
                DB::table('gross_items')->where($column, $value)->update([$fk => $existingId]);
            }
        }
    }
};
