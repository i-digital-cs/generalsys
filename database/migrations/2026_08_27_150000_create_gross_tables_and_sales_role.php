<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mohra Gross MVP (Phase 10) — docs/DATABASE_SCHEMA.md §22 + SALES role.
 * MySQL: ALTER ENUM. SQLite: rebuild users if SALES missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createGrossTables();
        $this->addCanConfirmGrossLoss();
        $this->addSalesRole();
    }

    public function down(): void
    {
        Schema::dropIfExists('gross_shipment_allocations');
        Schema::dropIfExists('gross_sales_invoice_lines');
        Schema::dropIfExists('gross_sales_invoices');
        Schema::dropIfExists('gross_invoice_sequences');
        Schema::dropIfExists('gross_customers');
        Schema::dropIfExists('gross_shipping_companies');
        Schema::dropIfExists('gross_stock_movements');
        Schema::dropIfExists('gross_stock_balances');
        Schema::dropIfExists('gross_items');
        Schema::dropIfExists('gross_categories');

        if (Schema::hasColumn('users', 'can_confirm_gross_loss')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('can_confirm_gross_loss');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('users')->where('role', 'SALES')->update(['role' => 'EMPLOYEE']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('MANAGER','TEAM_LEADER','EMPLOYEE','ACCOUNTANT','ASSISTANT','DRIVER') NOT NULL DEFAULT 'EMPLOYEE'");
        }
    }

    private function createGrossTables(): void
    {
        Schema::create('gross_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->index('idx_gross_cat_parent')
                ->constrained('gross_categories')->nullOnDelete();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->timestamps();
        });

        Schema::create('gross_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64);
            $table->string('name', 191);
            $table->string('color', 64)->nullable();
            $table->string('size', 64)->nullable();
            $table->string('model', 64)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()
                ->constrained('gross_categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()
                ->constrained('gross_categories')->nullOnDelete();
            $table->unsignedInteger('pieces_per_carton')->default(1);
            $table->unsignedInteger('reorder_level')->default(5);
            $table->decimal('default_sale_price', 12, 2)->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->index('idx_gross_item_status');
            $table->timestamps();
            $table->unique('code', 'uq_gross_item_code');
        });

        Schema::create('gross_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('gross_items')->cascadeOnDelete();
            $table->integer('qty_on_hand_pieces')->default(0);
            $table->integer('qty_with_courier_pieces')->default(0);
            $table->integer('qty_lost_pieces')->default(0);
            $table->timestamps();
            $table->unique('item_id', 'uq_gross_bal_item');
        });

        Schema::create('gross_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('gross_items')->cascadeOnDelete();
            $table->string('type', 32);
            $table->unsignedInteger('qty');
            $table->enum('unit', ['PIECE', 'CARTON']);
            $table->unsignedInteger('conversion_factor');
            $table->unsignedInteger('qty_pieces');
            $table->string('ref_type', 64)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['item_id', 'created_at'], 'idx_gross_mov_item');
            $table->index(['ref_type', 'ref_id'], 'idx_gross_mov_ref');
        });

        Schema::create('gross_shipping_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('gross_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('phone', 40);
            $table->text('address')->nullable();
            $table->timestamps();
            $table->index('phone', 'idx_gross_cust_phone');
            $table->index('name', 'idx_gross_cust_name');
        });

        Schema::create('gross_invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->char('year_month', 6);
            $table->unsignedInteger('last_number')->default(0);
            $table->unique('year_month', 'uq_gross_inv_seq');
        });

        Schema::create('gross_sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 32);
            $table->foreignId('customer_id')->nullable()
                ->constrained('gross_customers')->nullOnDelete();
            $table->string('customer_name', 191);
            $table->string('customer_phone', 40);
            $table->text('customer_address')->nullable();
            $table->foreignId('sales_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('shipping_company_id')->nullable()
                ->constrained('gross_shipping_companies')->nullOnDelete();
            $table->string('shipping_company_name', 191)->nullable();
            $table->enum('currency', ['EGP', 'AED']);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->enum('shipping_fee_status', ['NONE', 'PENDING', 'LOST', 'SETTLED'])->default('NONE');
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', ['DRAFT', 'CONFIRMED', 'DELIVERED', 'REFUSED', 'CANCELLED'])
                ->default('DRAFT')
                ->index('idx_gross_inv_status');
            $table->text('notes')->nullable();
            $table->timestamp('refused_at')->nullable();
            $table->foreignId('refused_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('refuse_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('invoice_number', 'uq_gross_inv_number');
            $table->index('created_at', 'idx_gross_inv_created');
        });

        Schema::create('gross_sales_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained('gross_sales_invoices')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()
                ->constrained('gross_items')->nullOnDelete();
            $table->string('item_code_snapshot', 64);
            $table->string('item_name_snapshot', 191);
            $table->string('color_snapshot', 64)->nullable();
            $table->string('size_snapshot', 64)->nullable();
            $table->string('model_snapshot', 64)->nullable();
            $table->unsignedInteger('qty');
            $table->enum('unit', ['PIECE', 'CARTON']);
            $table->unsignedInteger('conversion_factor_snapshot');
            $table->unsignedInteger('qty_pieces');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
            $table->index('sales_invoice_id', 'idx_gross_line_inv');
        });

        Schema::create('gross_shipment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_line_id')
                ->constrained('gross_sales_invoice_lines')->cascadeOnDelete();
            $table->foreignId('shipping_company_id')->nullable()
                ->constrained('gross_shipping_companies')->nullOnDelete();
            $table->unsignedInteger('conversion_factor_snapshot');
            $table->unsignedInteger('qty_shipped_pieces');
            $table->unsignedInteger('qty_delivered_pieces')->default(0);
            $table->unsignedInteger('qty_returned_pieces')->default(0);
            $table->unsignedInteger('qty_with_courier_pieces');
            $table->unsignedInteger('qty_lost_pieces')->default(0);
            $table->timestamps();
            $table->index('sales_invoice_line_id', 'idx_gross_alloc_line');
        });
    }

    private function addCanConfirmGrossLoss(): void
    {
        if (Schema::hasColumn('users', 'can_confirm_gross_loss')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_confirm_gross_loss')->default(false)->after('role');
        });
    }

    private function addSalesRole(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('MANAGER','TEAM_LEADER','EMPLOYEE','ACCOUNTANT','ASSISTANT','DRIVER','SALES') NOT NULL DEFAULT 'EMPLOYEE'");

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $sql = (string) (DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->sql ?? '');
        if ($sql === '' || str_contains($sql, "'SALES'")) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::create('users_sales_role_tmp', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('job_title_ar', 150)->nullable();
            $table->string('job_title_en', 150)->nullable();
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->enum('role', ['MANAGER', 'TEAM_LEADER', 'EMPLOYEE', 'ACCOUNTANT', 'ASSISTANT', 'DRIVER', 'SALES'])
                ->default('EMPLOYEE')
                ->index();
            $table->boolean('can_confirm_gross_loss')->default(false);
            $table->enum('currency', ['EGP', 'AED']);
            $table->enum('language_preference', ['ar', 'en'])->default('ar');
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->enum('shift_type_default', ['FULL_TIME', 'ONLINE_HALF', 'ONLINE_FULL'])->default('FULL_TIME');
            $table->json('shift_types_default')->nullable();
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->index();
            $table->rememberToken();
            $table->timestamps();
        });

        $newCols = Schema::getColumnListing('users_sales_role_tmp');
        $oldCols = Schema::getColumnListing('users');
        $columns = array_values(array_intersect($oldCols, $newCols));
        $quoted = implode(', ', array_map(fn (string $col) => '"'.$col.'"', $columns));
        DB::statement("INSERT INTO users_sales_role_tmp ({$quoted}) SELECT {$quoted} FROM users");

        Schema::drop('users');
        Schema::rename('users_sales_role_tmp', 'users');

        Schema::enableForeignKeyConstraints();
    }
};
