<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhAppVersionPolicy;
use App\Models\GeneralHouse\GhAppVersionPolicyAudit;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\GeneralHouse\App\GhAndroidAppService;
use App\Services\GeneralHouse\App\GhAppVersionPolicyService;
use App\Support\GeneralHouse\GhAppPublicationStatus;
use App\Support\GeneralHouse\GhAppUpdateMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppVersionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private string $apkBytes;

    private string $sha256;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apkBytes = "PK\x03\x04GH-TEST-APK-FIXTURE";
        $this->sha256 = hash('sha256', $this->apkBytes);

        Storage::fake('local');
        Storage::disk('local')->makeDirectory('gh-android');
        Storage::disk('local')->put('gh-android/current.apk', $this->apkBytes);
        Storage::disk('local')->put('gh-android/current.manifest.json', json_encode([
            'channel' => 'test',
            'version_name' => '1.0.2-test',
            'version_code' => 3,
            'package_id' => 'net.genral.generalhouse.test',
            'app_label' => 'GNRL Operations TEST',
            'download_filename' => 'GeneralHouse-TEST.apk',
            'artifact' => 'current.apk',
            'sha256' => $this->sha256,
            'built_at' => '2026-11-08T09:39:12Z',
            'distribution_approved' => true,
        ], JSON_THROW_ON_ERROR));

        config([
            'general_house.android_app.channel' => 'test',
            'general_house.android_app.distribution_enabled' => true,
        ]);

        app(GhAppVersionPolicyService::class)->syncAndroidPublish('test', [
            'version_name' => '1.0.2-test',
            'version_code' => 3,
            'package_id' => 'net.genral.generalhouse.test',
            'sha256' => $this->sha256,
            'update_url' => 'https://generalsys.idigital-cs.com/app/download/apk',
            'artifact_path' => 'gh-android/current.apk',
            'distribution_approved' => true,
        ]);
    }

    public function test_android_and_ios_policies_are_independent(): void
    {
        $android = GhAppVersionPolicy::query()->where('platform', 'android')->where('channel', 'test')->firstOrFail();
        $ios = GhAppVersionPolicy::query()->where('platform', 'ios')->where('channel', 'test')->firstOrFail();

        $this->assertSame(3, (int) $android->latest_build);
        $this->assertSame(0, (int) $ios->latest_build);
        $this->assertSame(GhAppPublicationStatus::UNPUBLISHED, $ios->publication_status);
    }

    public function test_version_check_optional_mandatory_and_none(): void
    {
        $service = app(GhAppVersionPolicyService::class);
        $policy = GhAppVersionPolicy::query()->where('platform', 'android')->where('channel', 'test')->firstOrFail();

        $none = $service->evaluateCheck('android', 3);
        $this->assertFalse($none['update_available']);
        $this->assertFalse($none['update_required']);

        $optional = $service->evaluateCheck('android', 1);
        $this->assertTrue($optional['update_available']);
        $this->assertFalse($optional['update_required']);

        $manager = User::factory()->manager()->create();
        $service->updatePolicy($manager, $policy, [
            'latest_version_name' => '1.0.2-test',
            'latest_build' => 3,
            'minimum_build' => 3,
            'update_mode' => GhAppUpdateMode::REQUIRED_BY_MINIMUM,
            'update_url' => $policy->update_url,
            'publication_status' => GhAppPublicationStatus::PUBLISHED,
            'release_notes_en' => 'Security update',
        ], 'force min');

        $required = $service->evaluateCheck('android', 1);
        $this->assertTrue($required['update_required']);
        $this->assertTrue($required['update_available']);
        $this->assertSame('Security update', $required['release_notes']);
    }

    public function test_minimum_cannot_exceed_latest_and_mandatory_needs_publish(): void
    {
        $manager = User::factory()->manager()->create();
        $policy = GhAppVersionPolicy::query()->where('platform', 'android')->where('channel', 'test')->firstOrFail();

        $this->actingAs($manager)
            ->put(route('admin.app.versions.update', $policy), [
                'latest_version_name' => '1.0.2-test',
                'latest_build' => 3,
                'minimum_build' => 5,
                'update_mode' => GhAppUpdateMode::OPTIONAL,
                'publication_status' => GhAppPublicationStatus::PUBLISHED,
                'update_url' => $policy->update_url,
            ])
            ->assertSessionHasErrors('minimum_build');

        $ios = GhAppVersionPolicy::query()->where('platform', 'ios')->where('channel', 'test')->firstOrFail();
        $this->actingAs($manager)
            ->put(route('admin.app.versions.update', $ios), [
                'latest_version_name' => '1.0.0',
                'latest_build' => 10,
                'minimum_build' => 10,
                'update_mode' => GhAppUpdateMode::REQUIRED_BY_MINIMUM,
                'publication_status' => GhAppPublicationStatus::PUBLISHED,
                'update_url' => null,
            ])
            ->assertSessionHasErrors();
    }

    public function test_version_check_api_global_and_compat_alias(): void
    {
        $ok = $this->getJson(route('app.version-check', [
            'platform' => 'android',
            'build_number' => 1,
            'version_name' => '1.0.0',
            'app_channel' => 'test',
        ]));
        $ok->assertOk()->assertJsonPath('service_ok', true)->assertJsonPath('channel', 'test');

        $compat = $this->getJson(route('gh.app.version-check', [
            'platform' => 'android',
            'build_number' => 1,
            'app_channel' => 'test',
        ]));
        $compat->assertOk()->assertJsonPath('service_ok', true);

        $bad = $this->getJson(route('app.version-check', [
            'platform' => 'android',
            'build_number' => 1,
            'app_channel' => 'live',
        ]));
        $bad->assertStatus(422)->assertJsonPath('service_ok', false);

        $invalid = $this->getJson(route('app.version-check', [
            'platform' => 'windows',
            'build_number' => 1,
        ]));
        $invalid->assertStatus(422);
    }

    public function test_test_and_live_policies_do_not_cross(): void
    {
        $live = GhAppVersionPolicy::query()->where('platform', 'android')->where('channel', 'live')->firstOrFail();
        $this->assertSame(0, (int) $live->latest_build);

        $result = app(GhAppVersionPolicyService::class)->evaluateCheck('android', 1);
        $this->assertSame('test', $result['channel']);
        $this->assertSame(3, $result['latest_build']);
    }

    public function test_manager_can_open_global_admin_page_others_forbidden(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('admin.app.versions'))
            ->assertOk()
            ->assertSee(__('gh.app_versions.title'), false);

        $this->actingAs($manager)->get(route('admin.settings'))
            ->assertOk()
            ->assertSee(__('nav.gh_app_versions'), false)
            ->assertSee('data-gnrl-app-versions-link="1"', false);

        $this->actingAs($manager)->get(route('gh.app.versions'))
            ->assertRedirect(route('admin.app.versions'));

        $driver = User::factory()->driver()->create();
        $this->actingAs($driver)->get(route('admin.app.versions'))->assertForbidden();

        $employee = User::factory()->create(['role' => 'EMPLOYEE']);
        $this->actingAs($employee)->put(route('admin.app.versions.update', GhAppVersionPolicy::query()->first()), [
            'latest_version_name' => 'x',
            'latest_build' => 1,
            'minimum_build' => 0,
            'update_mode' => GhAppUpdateMode::OPTIONAL,
            'publication_status' => GhAppPublicationStatus::UNPUBLISHED,
        ])->assertForbidden();
    }

    public function test_policy_changes_are_audited(): void
    {
        $manager = User::factory()->manager()->create();
        $policy = GhAppVersionPolicy::query()->where('platform', 'android')->where('channel', 'test')->firstOrFail();

        $this->actingAs($manager)->put(route('admin.app.versions.update', $policy), [
            'latest_version_name' => '1.0.2-test',
            'latest_build' => 3,
            'minimum_build' => 1,
            'update_mode' => GhAppUpdateMode::OPTIONAL,
            'publication_status' => GhAppPublicationStatus::PUBLISHED,
            'update_url' => $policy->update_url,
            'reason' => 'Lower minimum',
        ])->assertRedirect(route('admin.app.versions'));

        $this->assertDatabaseHas('gh_app_version_policy_audits', [
            'policy_id' => $policy->id,
            'actor_id' => $manager->id,
            'action' => 'policy.update',
            'reason' => 'Lower minimum',
        ]);
        $this->assertGreaterThan(0, GhAppVersionPolicyAudit::query()->count());
    }

    public function test_global_sidebar_footer_for_hr_and_gh_users(): void
    {
        $manager = User::factory()->manager()->create();
        $html = $this->actingAs($manager)->get(route('gh.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-gnrl-app-download-footer="1"', $html);
        $this->assertStringContainsString(route('app.download.file'), $html);
        $this->assertStringContainsString(__('nav.gh_app_download'), $html);
        $this->assertSame(1, substr_count($html, 'data-gnrl-app-download-footer="1"'));
        $this->assertStringNotContainsString(route('admin.app.versions'), $html);

        $driver = User::factory()->driver()->create();
        ValuationAssignment::query()->create(['user_id' => $driver->id, 'module' => 'service']);
        $driverHtml = $this->actingAs($driver)->get(route('gh.driver.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-gnrl-app-download-footer="1"', $driverHtml);
        $this->assertStringNotContainsString(route('admin.app.versions'), $driverHtml);

        $employee = User::factory()->create(['role' => 'EMPLOYEE']);
        $hr = $this->actingAs($employee)->get(route('employee.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-gnrl-app-download-footer="1"', $hr);
        $this->assertStringContainsString(route('app.download.file'), $hr);
    }

    public function test_direct_android_download_still_works(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('app.download.file'))
            ->assertOk()
            ->assertDownload('GeneralHouse-TEST.apk');
        $this->assertTrue(app(GhAndroidAppService::class)->isAvailable());
    }

    public function test_publish_does_not_auto_force_updates(): void
    {
        $policy = GhAppVersionPolicy::query()->where('platform', 'android')->where('channel', 'test')->firstOrFail();
        $this->assertSame(GhAppUpdateMode::OPTIONAL, $policy->update_mode);
        $this->assertSame(0, (int) $policy->minimum_build);
    }

    public function test_mandatory_boundary_and_at_minimum_is_not_required(): void
    {
        $service = app(GhAppVersionPolicyService::class);
        $policy = GhAppVersionPolicy::query()->where('platform', 'android')->where('channel', 'test')->firstOrFail();
        $manager = User::factory()->manager()->create();

        $service->updatePolicy($manager, $policy, [
            'latest_version_name' => '1.0.2-test',
            'latest_build' => 5,
            'minimum_build' => 4,
            'update_mode' => GhAppUpdateMode::REQUIRED_BY_MINIMUM,
            'update_url' => $policy->update_url,
            'publication_status' => GhAppPublicationStatus::PUBLISHED,
        ], 'set mandatory floor');

        $below = $service->evaluateCheck('android', 3);
        $this->assertTrue($below['update_required']);
        $this->assertTrue($below['update_available']);

        $atMin = $service->evaluateCheck('android', 4);
        $this->assertFalse($atMin['update_required']);
        $this->assertTrue($atMin['update_available']);

        $current = $service->evaluateCheck('android', 5);
        $this->assertFalse($current['update_required']);
        $this->assertFalse($current['update_available']);
    }

    public function test_missing_apk_blocks_mandatory_and_clears_update_signals(): void
    {
        Storage::disk('local')->delete('gh-android/current.apk');
        Storage::disk('local')->delete('gh-android/current.manifest.json');

        $service = app(GhAppVersionPolicyService::class);
        $policy = GhAppVersionPolicy::query()->where('platform', 'android')->where('channel', 'test')->firstOrFail();
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->put(route('admin.app.versions.update', $policy), [
                'latest_version_name' => '1.0.2-test',
                'latest_build' => 3,
                'minimum_build' => 3,
                'update_mode' => GhAppUpdateMode::REQUIRED_BY_MINIMUM,
                'publication_status' => GhAppPublicationStatus::PUBLISHED,
                'update_url' => null,
            ])
            ->assertSessionHasErrors();

        // Even if a stale URL remains while artifact is gone, evaluate must not force without APK readiness.
        $policy->refresh();
        $policy->update_url = 'https://generalsys.idigital-cs.com/app/download/apk';
        $policy->update_mode = GhAppUpdateMode::REQUIRED_BY_MINIMUM;
        $policy->minimum_build = 3;
        $policy->latest_build = 3;
        $policy->publication_status = GhAppPublicationStatus::PUBLISHED;
        $policy->save();

        $result = $service->evaluateCheck('android', 1);
        $this->assertFalse($result['update_required']);
        $this->assertFalse($result['update_available']);
    }

    public function test_unpublished_policy_never_forces_or_offers_update(): void
    {
        $service = app(GhAppVersionPolicyService::class);
        $policy = GhAppVersionPolicy::query()->where('platform', 'android')->where('channel', 'test')->firstOrFail();
        $manager = User::factory()->manager()->create();

        $service->updatePolicy($manager, $policy, [
            'latest_version_name' => '1.0.2-test',
            'latest_build' => 3,
            'minimum_build' => 3,
            'update_mode' => GhAppUpdateMode::OPTIONAL,
            'update_url' => $policy->update_url,
            'publication_status' => GhAppPublicationStatus::UNPUBLISHED,
        ], 'unpublish');

        $result = $service->evaluateCheck('android', 1);
        $this->assertFalse($result['update_required']);
        $this->assertFalse($result['update_available']);
        $this->assertSame(0, $result['latest_build']);
        $this->assertSame(0, $result['minimum_build']);
    }

    public function test_version_check_api_mandatory_payload(): void
    {
        $policy = GhAppVersionPolicy::query()->where('platform', 'android')->where('channel', 'test')->firstOrFail();
        $manager = User::factory()->manager()->create();
        app(GhAppVersionPolicyService::class)->updatePolicy($manager, $policy, [
            'latest_version_name' => '1.0.2-test',
            'latest_build' => 3,
            'minimum_build' => 3,
            'update_mode' => GhAppUpdateMode::REQUIRED_BY_MINIMUM,
            'update_url' => $policy->update_url,
            'publication_status' => GhAppPublicationStatus::PUBLISHED,
            'release_notes_en' => 'Must update',
        ], 'api mandatory');

        $this->getJson(route('app.version-check', [
            'platform' => 'android',
            'build_number' => 1,
            'app_channel' => 'test',
        ]))
            ->assertOk()
            ->assertJsonPath('service_ok', true)
            ->assertJsonPath('update_required', true)
            ->assertJsonPath('update_available', true)
            ->assertJsonPath('latest_build', 3)
            ->assertJsonPath('minimum_build', 3);
    }
}
