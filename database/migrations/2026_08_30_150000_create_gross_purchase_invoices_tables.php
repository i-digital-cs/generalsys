<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gross_purchase_invoices')) {
            return;
        }

        Schema::create('gross_purchase_invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->char('year_month', 6);
            $table->unsignedInteger('last_number')->default(0);
            $table->unique('year_month', 'uq_gross_pur_inv_seq');
        });

        Schema::create('gross_purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 32);
            $table->foreignId('supplier_id')->nullable()
                ->constrained('gross_suppliers')->nullOnDelete();
            $table->string('supplier_name', 191);
            $table->string('supplier_phone', 40);
            $table->text('supplier_address')->nullable();
            $table->enum('currency', ['EGP', 'AED']);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('status', ['DRAFT', 'CONFIRMED', 'CANCELLED'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique('invoice_number', 'uq_gross_pur_inv_number');
            $table->index('status', 'idx_gross_pur_inv_status');
            $table->index('supplier_id', 'idx_gross_pur_inv_supplier');
        });

        Schema::create('gross_purchase_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')
                ->constrained('gross_purchase_invoices')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()
                ->constrained('gross_items')->nullOnDelete();
            $table->string('item_code_snapshot', 64);
            $table->string('item_name_snapshot', 191);
            $table->string('color_snapshot', 120)->nullable();
            $table->string('size_snapshot', 120)->nullable();
            $table->string('model_snapshot', 120)->nullable();
            $table->unsignedInteger('qty');
            $table->enum('unit', ['PIECE', 'CARTON'])->default('PIECE');
            $table->unsignedInteger('conversion_factor_snapshot');
            $table->unsignedInteger('qty_pieces');
            $table->decimal('buy_price', 12, 2);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
            $table->index('purchase_invoice_id', 'idx_gross_pur_line_inv');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gross_purchase_invoice_lines');
        Schema::dropIfExists('gross_purchase_invoices');
        Schema::dropIfExists('gross_purchase_invoice_sequences');
    }
};
