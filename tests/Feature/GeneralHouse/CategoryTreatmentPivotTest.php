<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryTreatmentPivotTest extends TestCase
{
    use RefreshDatabase;

    public function test_gh_category_treatment_table_exists_with_unique_pair(): void
    {
        $this->assertTrue(Schema::hasTable('gh_category_treatment'));
        $this->assertTrue(Schema::hasColumns('gh_category_treatment', [
            'id',
            'category_id',
            'treatment_id',
            'sort_order',
            'created_at',
            'updated_at',
        ]));

        $category = GhServiceCategory::factory()->create();
        $treatment = GhTreatment::factory()->create();

        $category->treatments()->attach($treatment->id, ['sort_order' => 1]);

        $this->assertDatabaseHas('gh_category_treatment', [
            'category_id' => $category->id,
            'treatment_id' => $treatment->id,
            'sort_order' => 1,
        ]);
        $this->assertTrue($treatment->categories->contains('id', $category->id));

        $this->expectException(QueryException::class);
        DB::table('gh_category_treatment')->insert([
            'category_id' => $category->id,
            'treatment_id' => $treatment->id,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_pivot_starts_empty_without_backfill(): void
    {
        GhServiceCategory::factory()->create();
        GhTreatment::factory()->create();

        $this->assertSame(0, DB::table('gh_category_treatment')->count());
    }
}
