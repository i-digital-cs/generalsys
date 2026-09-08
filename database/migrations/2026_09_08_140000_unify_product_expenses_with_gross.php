<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth: Gross expense types + treasury EXPENSE payments.
 * Product calculated expenses keep only monthly expected budgets keyed by expense_type_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gross_expense_types', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(50)->after('is_active');
            $table->boolean('supports_rent_period')->default(false)->after('sort_order');
        });

        DB::table('gross_expense_types')->where('name_en', 'Rent')->update([
            'supports_rent_period' => true,
            'sort_order' => 20,
        ]);
        DB::table('gross_expense_types')->where('name_en', 'Electricity')->update([
            'sort_order' => 30,
        ]);
        DB::table('gross_expense_types')->where('name_en', 'Other')->update([
            'sort_order' => 90,
        ]);

        if (! DB::table('gross_expense_types')->where('name_en', 'Petrol')->exists()) {
            DB::table('gross_expense_types')->insert([
                'name_ar' => 'بنزين',
                'name_en' => 'Petrol',
                'is_active' => true,
                'sort_order' => 10,
                'supports_rent_period' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('gross_expense_types')->where('name_en', 'Petrol')->update(['sort_order' => 10]);
        }

        Schema::dropIfExists('product_expense_entries');
        Schema::dropIfExists('product_expense_month_budgets');
        Schema::dropIfExists('product_expense_categories');

        Schema::create('product_expense_month_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_type_id')->constrained('gross_expense_types')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('expected_amount', 12, 2);
            $table->enum('currency', ['EGP', 'AED'])->default('EGP');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['expense_type_id', 'year', 'month'], 'uq_prod_exp_budget_type');
            $table->index(['year', 'month'], 'idx_prod_exp_budget_ym');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_expense_month_budgets');

        Schema::table('gross_expense_types', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'supports_rent_period']);
        });

        // Recreate empty prior structure only if rolling back locally.
        Schema::create('product_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('slug', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('supports_rent_period')->default(false);
            $table->timestamps();
        });
    }
};
