<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::create('product_expense_month_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('product_expense_categories')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('expected_amount', 12, 2);
            $table->enum('currency', ['EGP', 'AED'])->default('EGP');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'year', 'month'], 'uq_prod_exp_budget');
            $table->index(['year', 'month'], 'idx_prod_exp_budget_ym');
        });

        Schema::create('product_expense_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('product_expense_categories')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 12, 2);
            $table->date('entry_date');
            $table->enum('rent_period', ['THIS_MONTH', 'NEXT_MONTH'])->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['category_id', 'year', 'month'], 'idx_prod_exp_entry_cat_ym');
            $table->index('entry_date', 'idx_prod_exp_entry_date');
        });

        $now = now();
        DB::table('product_expense_categories')->insert([
            [
                'name_ar' => 'بنزين',
                'name_en' => 'Petrol',
                'slug' => 'petrol',
                'is_active' => true,
                'sort_order' => 10,
                'supports_rent_period' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_ar' => 'إيجار',
                'name_en' => 'Rent',
                'slug' => 'rent',
                'is_active' => true,
                'sort_order' => 20,
                'supports_rent_period' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_ar' => 'كهرباء',
                'name_en' => 'Electricity',
                'slug' => 'electricity',
                'is_active' => true,
                'sort_order' => 30,
                'supports_rent_period' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name_ar' => 'أخرى',
                'name_en' => 'Other',
                'slug' => 'other',
                'is_active' => true,
                'sort_order' => 90,
                'supports_rent_period' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_expense_entries');
        Schema::dropIfExists('product_expense_month_budgets');
        Schema::dropIfExists('product_expense_categories');
    }
};
