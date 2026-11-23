<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner-approved TEST mappings for gh_category_treatment (Option B).
 * Idempotent insert only — does not touch offerings, orders, or invoices.
 *
 * Pairs: (1,1) (1,2) (1,3) (2,5). Category 3 intentionally unmapped.
 */
return new class extends Migration
{
    /** @var list<array{category_id: int, treatment_id: int, category_en: string, treatment_en: string}> */
    private array $approved = [
        ['category_id' => 1, 'treatment_id' => 1, 'category_en' => 'Laundry Service', 'treatment_en' => 'Wash Only'],
        ['category_id' => 1, 'treatment_id' => 2, 'category_en' => 'Laundry Service', 'treatment_en' => 'Wash & Iron'],
        ['category_id' => 1, 'treatment_id' => 3, 'category_en' => 'Laundry Service', 'treatment_en' => 'Iron Only'],
        ['category_id' => 2, 'treatment_id' => 5, 'category_en' => 'Deep Cleaning', 'treatment_en' => 'Deep Cleaning'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('gh_category_treatment')) {
            return;
        }

        $now = now();
        foreach ($this->approved as $row) {
            $category = DB::table('gh_service_categories')->where('id', $row['category_id'])->first();
            $treatment = DB::table('gh_treatments')->where('id', $row['treatment_id'])->first();

            // Skip silently on empty scratch DBs (PHPUnit). Fail hard on identity drift when IDs exist.
            if ($category === null || $treatment === null) {
                continue;
            }

            if ((string) $category->name_en !== $row['category_en']
                || (string) $treatment->name_en !== $row['treatment_en']) {
                throw new RuntimeException(sprintf(
                    'Approved GH mapping identity mismatch for (%d,%d): expected [%s × %s], found [%s × %s]',
                    $row['category_id'],
                    $row['treatment_id'],
                    $row['category_en'],
                    $row['treatment_en'],
                    (string) $category->name_en,
                    (string) $treatment->name_en
                ));
            }

            $exists = DB::table('gh_category_treatment')
                ->where('category_id', $row['category_id'])
                ->where('treatment_id', $row['treatment_id'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('gh_category_treatment')->insert([
                'category_id' => $row['category_id'],
                'treatment_id' => $row['treatment_id'],
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('gh_category_treatment')) {
            return;
        }

        foreach ($this->approved as $row) {
            DB::table('gh_category_treatment')
                ->where('category_id', $row['category_id'])
                ->where('treatment_id', $row['treatment_id'])
                ->delete();
        }
    }
};
