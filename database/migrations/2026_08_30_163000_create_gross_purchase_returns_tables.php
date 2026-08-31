<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gross_purchase_invoice_lines')
            && ! Schema::hasColumn('gross_purchase_invoice_lines', 'qty_returned_pieces')) {
            Schema::table('gross_purchase_invoice_lines', function (Blueprint $table) {
                $table->unsignedInteger('qty_returned_pieces')->default(0)->after('qty_pieces');
            });
        }

        if (! Schema::hasTable('gross_purchase_return_sequences')) {
            Schema::create('gross_purchase_return_sequences', function (Blueprint $table) {
                $table->id();
                $table->char('year_month', 6);
                $table->unsignedInteger('last_number')->default(0);
                $table->unique('year_month', 'uq_gross_pur_ret_seq');
            });
        }

        if (! Schema::hasTable('gross_purchase_returns')) {
            Schema::create('gross_purchase_returns', function (Blueprint $table) {
                $table->id();
                $table->string('return_number', 32);
                $table->foreignId('purchase_invoice_id')
                    ->constrained('gross_purchase_invoices')->cascadeOnDelete();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique('return_number', 'uq_gross_pur_ret_number');
                $table->index('purchase_invoice_id', 'idx_gross_pur_ret_inv');
            });
        }

        if (! Schema::hasTable('gross_purchase_return_lines')) {
            Schema::create('gross_purchase_return_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_return_id')
                    ->constrained('gross_purchase_returns')->cascadeOnDelete();
                $table->foreignId('purchase_invoice_line_id')
                    ->constrained('gross_purchase_invoice_lines')->cascadeOnDelete();
                $table->foreignId('item_id')->nullable()
                    ->constrained('gross_items')->nullOnDelete();
                $table->unsignedInteger('qty');
                $table->enum('unit', ['PIECE', 'CARTON'])->default('PIECE');
                $table->unsignedInteger('conversion_factor');
                $table->unsignedInteger('qty_pieces');
                $table->decimal('buy_price', 12, 2);
                $table->decimal('line_total', 12, 2);
                $table->timestamps();
                $table->index('purchase_return_id', 'idx_gross_pur_ret_line_ret');
                $table->index('purchase_invoice_line_id', 'idx_gross_pur_ret_line_src');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gross_purchase_return_lines');
        Schema::dropIfExists('gross_purchase_returns');
        Schema::dropIfExists('gross_purchase_return_sequences');

        if (Schema::hasColumn('gross_purchase_invoice_lines', 'qty_returned_pieces')) {
            Schema::table('gross_purchase_invoice_lines', function (Blueprint $table) {
                $table->dropColumn('qty_returned_pieces');
            });
        }
    }
};
