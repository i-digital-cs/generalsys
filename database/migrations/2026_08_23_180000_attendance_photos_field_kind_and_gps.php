<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-7.10 — Driver mid-day field photos: kind FIELD + GPS columns on attendance_photos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE attendance_photos MODIFY COLUMN kind ENUM('CLOCK_IN','CLOCK_OUT','FIELD') NOT NULL");
        } elseif ($driver === 'sqlite') {
            $this->rebuildSqlitePhotosTable(['CLOCK_IN', 'CLOCK_OUT', 'FIELD']);
        }

        Schema::table('attendance_photos', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_photos', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('description');
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
                $table->unsignedInteger('accuracy_m')->nullable()->after('lng');
                $table->unsignedInteger('distance_m')->nullable()->after('accuracy_m');
                $table->boolean('outside_geofence')->default(false)->after('distance_m');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_photos', function (Blueprint $table) {
            foreach (['lat', 'lng', 'accuracy_m', 'distance_m', 'outside_geofence'] as $col) {
                if (Schema::hasColumn('attendance_photos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::table('attendance_photos')->where('kind', 'FIELD')->delete();
            DB::statement("ALTER TABLE attendance_photos MODIFY COLUMN kind ENUM('CLOCK_IN','CLOCK_OUT') NOT NULL");
        } elseif ($driver === 'sqlite') {
            DB::table('attendance_photos')->where('kind', 'FIELD')->delete();
            $this->rebuildSqlitePhotosTable(['CLOCK_IN', 'CLOCK_OUT']);
        }
    }

    /**
     * @param  list<string>  $kinds
     */
    private function rebuildSqlitePhotosTable(array $kinds): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('attendance_photos_field_tmp', function (Blueprint $table) use ($kinds) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            if (Schema::hasColumn('attendance_photos', 'attendance_segment_id')) {
                $table->foreignId('attendance_segment_id')->nullable()
                    ->constrained('attendance_segments')->nullOnDelete();
            }
            $table->enum('kind', $kinds);
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->index(['attendance_id', 'kind'], 'idx_att_photos_att_kind_tmp');
        });

        $columns = [
            'id', 'attendance_id', 'kind', 'file_path', 'original_name', 'description',
            'mime_type', 'size_bytes', 'sort_order', 'uploaded_at', 'created_at', 'updated_at',
        ];
        if (Schema::hasColumn('attendance_photos', 'attendance_segment_id')) {
            array_splice($columns, 2, 0, ['attendance_segment_id']);
        }

        $select = implode(', ', $columns);
        DB::statement("INSERT INTO attendance_photos_field_tmp ({$select}) SELECT {$select} FROM attendance_photos");

        Schema::drop('attendance_photos');
        Schema::rename('attendance_photos_field_tmp', 'attendance_photos');

        Schema::enableForeignKeyConstraints();
    }
};
