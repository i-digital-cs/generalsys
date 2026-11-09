<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive audit trail for payroll status transitions (e.g. APPROVED → DRAFT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_status_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_record_id')->constrained('payroll_records')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 16);
            $table->string('to_status', 16);
            $table->string('action', 64);
            $table->string('reason', 500)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['payroll_record_id', 'recorded_at'], 'idx_pay_status_audit_payroll');
            $table->index(['user_id', 'recorded_at'], 'idx_pay_status_audit_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_status_audits');
    }
};
