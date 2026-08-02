<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // docs/DATABASE_SCHEMA.md §2 — employee_profiles
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('employee_code', 50)->unique();
            $table->string('national_id', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->enum('gender', ['MALE', 'FEMALE'])->nullable();
            $table->string('religion', 50)->nullable();
            $table->enum('marital_status', ['SINGLE', 'MARRIED', 'DIVORCED', 'WIDOWED'])->nullable();
            $table->string('personal_photo_path')->nullable();
            $table->timestamps();
        });

        // docs/DATABASE_SCHEMA.md §3 — employee_qualifications
        Schema::create('employee_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('degree', 150);
            $table->string('specialization', 150)->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->timestamps();
        });

        // docs/DATABASE_SCHEMA.md §4 — employee_documents
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['ID_CARD', 'QUALIFICATION', 'BIRTH_CERTIFICATE', 'CRIMINAL_RECORD', 'CONTRACT_PDF', 'OTHER']);
            $table->string('file_path');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });

        // docs/DATABASE_SCHEMA.md §5 — emergency_contacts
        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30);
            $table->string('relationship', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employee_qualifications');
        Schema::dropIfExists('employee_profiles');
    }
};
