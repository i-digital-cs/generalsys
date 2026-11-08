<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GNRL Operations app version policies (Android/iOS × TEST/LIVE) + audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gh_app_version_policies')) {
            Schema::create('gh_app_version_policies', function (Blueprint $table) {
                $table->id();
                $table->string('platform', 16); // android | ios
                $table->string('channel', 8); // test | live
                $table->string('latest_version_name', 50)->nullable();
                $table->unsignedInteger('latest_build')->default(0);
                $table->unsignedInteger('minimum_build')->default(0);
                $table->string('update_mode', 32)->default('OPTIONAL'); // OPTIONAL | REQUIRED_BY_MINIMUM
                $table->string('update_url', 500)->nullable();
                $table->text('release_notes_ar')->nullable();
                $table->text('release_notes_en')->nullable();
                $table->string('publication_status', 16)->default('UNPUBLISHED'); // UNPUBLISHED | PUBLISHED
                $table->string('package_id', 191)->nullable();
                $table->char('sha256', 64)->nullable();
                $table->string('artifact_path', 255)->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique(['platform', 'channel'], 'uq_gh_app_version_platform_channel');
                $table->index(['channel', 'platform'], 'idx_gh_app_version_channel_platform');
            });
        }

        if (! Schema::hasTable('gh_app_version_policy_audits')) {
            Schema::create('gh_app_version_policy_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('policy_id')->constrained('gh_app_version_policies')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 50);
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->string('reason', 500)->nullable();
                $table->timestamp('recorded_at');
                $table->timestamps();

                $table->index(['policy_id', 'recorded_at'], 'idx_gh_app_version_audit_policy');
            });
        }

        $now = now();
        foreach (['android', 'ios'] as $platform) {
            foreach (['test', 'live'] as $channel) {
                $exists = DB::table('gh_app_version_policies')
                    ->where('platform', $platform)
                    ->where('channel', $channel)
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::table('gh_app_version_policies')->insert([
                    'platform' => $platform,
                    'channel' => $channel,
                    'latest_version_name' => null,
                    'latest_build' => 0,
                    'minimum_build' => 0,
                    'update_mode' => 'OPTIONAL',
                    'update_url' => null,
                    'release_notes_ar' => null,
                    'release_notes_en' => null,
                    'publication_status' => 'UNPUBLISHED',
                    'package_id' => $platform === 'android'
                        ? ($channel === 'test' ? 'net.genral.generalhouse.test' : 'net.genral.generalhouse')
                        : null,
                    'sha256' => null,
                    'artifact_path' => null,
                    'updated_by' => null,
                    'published_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_app_version_policy_audits');
        Schema::dropIfExists('gh_app_version_policies');
    }
};
