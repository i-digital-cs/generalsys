<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for manager changes to the GH standard VAT rate setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gh_vat_rate_audits')) {
            return;
        }

        Schema::create('gh_vat_rate_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->decimal('old_rate', 5, 2)->nullable();
            $table->decimal('new_rate', 5, 2);
            $table->string('reason', 500);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_vat_rate_audits');
    }
};
