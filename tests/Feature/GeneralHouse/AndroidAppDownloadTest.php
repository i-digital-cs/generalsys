<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\GeneralHouse\App\GhAndroidAppService;
use App\Support\GeneralHouse\GhServiceMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AndroidAppDownloadTest extends TestCase
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
    }

    public function test_manager_can_download_and_sees_global_footer_not_gh_menu_item(): void
    {
        $manager = User::factory()->manager()->create();

        $routes = array_column(
            GhServiceMenu::filterForUser($manager, config('module_menu.service')),
            'route',
        );
        $this->assertNotContains('gh.app.download', $routes);
        $this->assertNotContains('gh.app.versions', $routes);
        $this->assertNotContains('admin.app.versions', $routes);

        $page = $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('app.download'));
        $page->assertOk()
            ->assertSee('GNRL Operations TEST', false)
            ->assertSee(__('gh.app.test_banner'), false)
            ->assertSee(route('app.download.file'), false);

        $html = $this->actingAs($manager)->get(route('gh.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-gnrl-app-download-footer="1"', $html);
        $this->assertStringContainsString(route('app.download.file'), $html);

        $download = $this->actingAs($manager)->get(route('app.download.file'));
        $download->assertOk();
        $download->assertDownload('GeneralHouse-TEST.apk');
        $this->assertStringContainsString(
            GhAndroidAppService::MIME_APK,
            (string) $download->headers->get('content-type')
        );

        $file = $download->baseResponse->getFile();
        $body = file_get_contents($file->getPathname());
        $this->assertSame($this->apkBytes, $body);
        $this->assertSame($this->sha256, hash('sha256', (string) $body));
    }

    public function test_driver_footer_in_arabic_and_english(): void
    {
        $driver = User::factory()->driver()->create();
        ValuationAssignment::query()->create([
            'user_id' => $driver->id,
            'module' => 'service',
        ]);

        foreach (['en', 'ar'] as $locale) {
            $html = $this->actingAs($driver)
                ->withSession(['locale' => $locale])
                ->get(route('gh.driver.dashboard'))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('data-gnrl-app-download-footer="1"', $html);
            $this->assertStringContainsString(__('nav.gh_app_download', [], $locale), $html);
            $this->assertStringContainsString(route('app.download.file'), $html);
        }

        $this->actingAs($driver)->get(route('app.download'))->assertOk();
        $this->actingAs($driver)->get(route('app.download.file'))->assertOk();
    }

    public function test_hr_employee_without_gh_access_can_download_and_sees_footer(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE']);

        $this->actingAs($employee)->get(route('app.download'))->assertOk();
        $this->actingAs($employee)->get(route('app.download.file'))->assertOk();

        $html = $this->actingAs($employee)->get(route('employee.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-gnrl-app-download-footer="1"', $html);
        $this->assertStringContainsString(route('app.download.file'), $html);
    }

    public function test_service_assigned_employee_can_download(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE']);
        ValuationAssignment::query()->create([
            'user_id' => $employee->id,
            'module' => 'service',
        ]);

        $this->actingAs($employee)->get(route('app.download'))->assertOk();
        $this->actingAs($employee)->get(route('app.download.file'))->assertOk();
    }

    public function test_legacy_gh_download_urls_redirect_to_global(): void
    {
        $employee = User::factory()->create(['role' => 'EMPLOYEE']);

        $this->actingAs($employee)
            ->get(route('gh.app.download'))
            ->assertRedirect(route('app.download'));

        $this->actingAs($employee)
            ->get(route('gh.app.download.file'))
            ->assertRedirect(route('app.download.file'));
    }

    public function test_guest_is_redirected_to_login_for_download(): void
    {
        $this->get(route('app.download'))->assertRedirect();
        $this->get(route('app.download.file'))->assertRedirect();
        $this->get(route('gh.app.download'))->assertRedirect();
    }

    public function test_inactive_user_cannot_download(): void
    {
        $employee = User::factory()->inactive()->create(['role' => 'EMPLOYEE']);

        // Global inactive middleware redirects before the download controller.
        $this->actingAs($employee)->get(route('app.download'))
            ->assertRedirect(route('account.inactive'));
        $this->actingAs($employee)->get(route('app.download.file'))
            ->assertRedirect(route('account.inactive'));
    }

    public function test_live_channel_hides_download_without_approved_artifact(): void
    {
        config([
            'general_house.android_app.channel' => 'live',
            'general_house.android_app.distribution_enabled' => true,
        ]);

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('app.download'))->assertNotFound();
        $this->actingAs($manager)->get(route('app.download.file'))->assertNotFound();
    }

    public function test_distribution_disabled_hides_download(): void
    {
        config(['general_house.android_app.distribution_enabled' => false]);

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('app.download'))->assertNotFound();
    }
}
