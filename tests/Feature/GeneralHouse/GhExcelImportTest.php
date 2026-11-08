<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Import\GhImageImportException;
use App\Services\GeneralHouse\Import\GhImportService;
use App\Services\GeneralHouse\Import\GhSafeImageFetcher;
use App\Services\GeneralHouse\Import\GhSpreadsheetSupport;
use App\Services\GeneralHouse\Import\GhZipImageArchive;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class GhExcelImportTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach (array_merge($this->tempFiles, glob(storage_path('app/tmp/gh-import-*.xlsx')) ?: []) as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** @param  list<list<string>>  $rows */
    private function xlsx(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'ghx').'.xlsx';
        (new Xlsx($book))->save($path);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'import.xlsx', GhSpreadsheetSupport::XLSX_MIME, null, true);
    }

    /** @param  array<string, string>  $entries name => contents */
    private function zip(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ghz').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'images.zip', 'application/zip', null, true);
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function manager(): User
    {
        return User::factory()->manager()->create();
    }

    private function tokenFrom(TestResponse $response): string
    {
        $response->assertRedirect();

        return basename((string) $response->headers->get('Location'));
    }

    /** @param  array<string, UploadedFile>  $files */
    private function preview(User $user, string $entity, array $files): TestResponse
    {
        return $this->actingAs($user)->post(route('gh.import.preview.store', ['entity' => $entity]), $files);
    }

    public function test_category_import_creates_all_valid_rows_after_preview_and_confirm(): void
    {
        $manager = $this->manager();
        $file = $this->xlsx([
            ['name_ar', 'name_en', 'description_ar', 'description_en', 'status', 'sort_order'],
            ['كندورة', 'Kandura', 'وصف', 'Desc', 'ACTIVE', '1'],
            ['عبايات', 'Abaya', '', '', 'active', '2'],
            ['بدلات', 'Suits', '', '', '', ''],
        ]);

        $token = $this->tokenFrom($this->preview($manager, 'categories', ['file' => $file]));

        $this->actingAs($manager)->get(route('gh.import.preview', ['entity' => 'categories', 'token' => $token]))
            ->assertOk()
            ->assertSee(__('gh.import.preview.title'));
        $this->assertSame(0, GhServiceCategory::query()->count());

        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'categories', 'token' => $token]))
            ->assertRedirect(route('gh.categories.index'))
            ->assertSessionHas('success');

        $this->assertSame(3, GhServiceCategory::query()->count());
        $this->assertDatabaseHas('gh_service_categories', ['name_en' => 'Abaya', 'status' => 'ACTIVE', 'sort_order' => 2]);
        $this->assertDatabaseHas('gh_service_categories', ['name_en' => 'Suits', 'status' => 'ACTIVE', 'sort_order' => 0]);
    }

    public function test_duplicates_are_skipped_case_insensitively_and_never_overwrite(): void
    {
        $manager = $this->manager();
        GhServiceCategory::factory()->create(['name_ar' => 'الأصلي', 'name_en' => 'Kandura']);
        $file = $this->xlsx([
            ['name_ar', 'name_en'],
            ['جديد', 'KANDURA'],
            ['عبايات', 'Abaya'],
            ['عبايات مكررة', 'abaya'],
        ]);

        $token = $this->tokenFrom($this->preview($manager, 'categories', ['file' => $file]));
        $preview = app(GhImportService::class)->previewFor('categories', $token, $manager);
        $this->assertSame(2, $preview->duplicate);
        $this->assertSame(1, $preview->create);

        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'categories', 'token' => $token]));

        $this->assertSame(2, GhServiceCategory::query()->count());
        $this->assertDatabaseHas('gh_service_categories', ['name_ar' => 'الأصلي', 'name_en' => 'Kandura']);
        $this->assertDatabaseMissing('gh_service_categories', ['name_ar' => 'جديد']);
    }

    public function test_missing_required_column_is_rejected_with_clear_error(): void
    {
        $manager = $this->manager();
        $file = $this->xlsx([['name_ar', 'status'], ['كندورة', 'ACTIVE']]);

        $this->preview($manager, 'categories', ['file' => $file])
            ->assertSessionHasErrorsIn('ghImport', 'file');

        $this->assertSame(0, GhServiceCategory::query()->count());
    }

    public function test_product_import_resolves_category_by_english_name(): void
    {
        $manager = $this->manager();
        $category = GhServiceCategory::factory()->create(['name_en' => 'Kandura']);
        $file = $this->xlsx([
            ['code', 'name_ar', 'name_en', 'category_name_en', 'status', 'sort_order'],
            ['kan-001', 'كندورة رجالي', 'Men Kandura', 'kandura', 'ACTIVE', '1'],
            ['BAD-001', 'منتج', 'Orphan', 'Unknown Category', 'ACTIVE', '2'],
        ]);

        $token = $this->tokenFrom($this->preview($manager, 'products', ['file' => $file]));
        $preview = app(GhImportService::class)->previewFor('products', $token, $manager);
        $this->assertSame(1, $preview->create);
        $this->assertSame(1, $preview->invalid);
        $this->assertStringContainsString('Unknown Category', $preview->errors[0]['message']);

        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'products', 'token' => $token]));

        $product = GhProduct::query()->where('code', 'KAN-001')->firstOrFail();
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame(1, GhProduct::query()->count());
    }

    public function test_non_manager_gets_403_for_template_preview_and_confirm(): void
    {
        $employee = User::factory()->create();
        $file = $this->xlsx([['name_ar', 'name_en'], ['كندورة', 'Kandura']]);

        $this->actingAs($employee)->get(route('gh.import.template', ['entity' => 'categories']))->assertForbidden();
        $this->preview($employee, 'categories', ['file' => $file])->assertForbidden();
        $this->actingAs($employee)->post(route('gh.import.confirm', ['entity' => 'categories', 'token' => 'x']))->assertForbidden();
        $this->assertSame(0, GhServiceCategory::query()->count());
    }

    #[DataProvider('entityProvider')]
    public function test_index_pages_show_the_import_card_in_both_locales(string $entity): void
    {
        $manager = $this->manager();

        foreach (['en', 'ar'] as $locale) {
            $this->actingAs($manager)->withSession(['locale' => $locale])->get(route('gh.'.$entity.'.index'))
                ->assertOk()
                ->assertSee(route('gh.import.template', ['entity' => $entity]), false)
                ->assertSee(route('gh.import.preview.store', ['entity' => $entity]), false)
                ->assertSee(trans('gh.import.download_example', [], $locale));
        }
    }

    public function test_orders_and_financial_documents_have_no_import_route(): void
    {
        $manager = $this->manager();

        foreach (['orders', 'invoices', 'payments', 'refunds', 'credit-notes'] as $entity) {
            $this->actingAs($manager)->get('/service/gh/import/'.$entity.'/template')->assertNotFound();
        }
    }

    /** @return array<string, list<string>> */
    public static function entityProvider(): array
    {
        return [
            'categories' => ['categories', 'general-house-service-categories-example.xlsx'],
            'treatments' => ['treatments', 'general-house-treatments-example.xlsx'],
            'products' => ['products', 'general-house-products-example.xlsx'],
            'offerings' => ['offerings', 'general-house-offerings-example.xlsx'],
            'zones' => ['zones', 'general-house-zones-example.xlsx'],
            'customers' => ['customers', 'general-house-customers-example.xlsx'],
            'contracts' => ['contracts', 'general-house-b2b-contracts-example.xlsx'],
        ];
    }

    #[DataProvider('entityProvider')]
    public function test_template_download_is_xlsx_with_three_sheets_and_examples(string $entity, string $filename): void
    {
        $response = $this->actingAs($this->manager())->get(route('gh.import.template', ['entity' => $entity]));

        $response->assertOk();
        $this->assertStringContainsString($filename, (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));

        $path = $response->baseResponse->getFile()->getPathname();
        $book = IOFactory::load($path);
        $this->assertSame(['Import Data', 'Instructions', 'Reference Values'], $book->getSheetNames());
        $exampleRows = $book->getSheet(0)->getHighestDataRow() - 1;
        $this->assertGreaterThanOrEqual(5, $exampleRows);
        $this->assertLessThanOrEqual(8, $exampleRows);
        $this->assertGreaterThan(2, $book->getSheet(1)->getHighestDataRow());
    }

    public function test_preview_is_bound_to_the_uploading_user_and_confirm_is_single_use(): void
    {
        $manager = $this->manager();
        $other = $this->manager();
        $file = $this->xlsx([['name_ar', 'name_en'], ['كندورة', 'Kandura']]);
        $token = $this->tokenFrom($this->preview($manager, 'treatments', ['file' => $file]));

        $this->actingAs($other)->get(route('gh.import.preview', ['entity' => 'treatments', 'token' => $token]))
            ->assertRedirect(route('gh.treatments.index'))
            ->assertSessionHas('error');

        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'treatments', 'token' => $token]));
        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'treatments', 'token' => $token]))
            ->assertSessionHas('error');

        $this->assertSame(1, GhTreatment::query()->count());
    }

    public function test_zip_with_path_traversal_is_rejected_and_nothing_is_written(): void
    {
        $zip = $this->zip(['../evil.jpg' => $this->png()]);

        try {
            app(GhZipImageArchive::class)->extract($zip->getRealPath(), storage_path('app/gh-import-tmp/test-traversal'));
            $this->fail('Traversal ZIP must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('zip', $e->errors());
        }
        $this->assertFileDoesNotExist(storage_path('app/gh-import-tmp/evil.jpg'));

        $manager = $this->manager();
        GhServiceCategory::factory()->create(['name_en' => 'Kandura']);
        $file = $this->xlsx([['code', 'name_ar', 'name_en', 'category_name_en'], ['K1', 'ك', 'K', 'Kandura']]);
        $this->preview($manager, 'products', ['file' => $file, 'zip' => $zip])
            ->assertSessionHasErrorsIn('ghImport', 'zip');
        $this->assertSame(0, GhProduct::query()->count());
    }

    public function test_zip_with_executable_is_rejected(): void
    {
        $zip = $this->zip(['shell.php' => '<?php echo 1;', 'a.jpg' => $this->png()]);

        $this->expectException(ValidationException::class);
        app(GhZipImageArchive::class)->extract($zip->getRealPath(), storage_path('app/gh-import-tmp/test-exec'));
    }

    public function test_private_and_unsafe_image_urls_are_rejected(): void
    {
        Http::fake();
        $fetcher = app(GhSafeImageFetcher::class);

        foreach ([
            'https://127.0.0.1/a.png',
            'https://10.0.0.5/a.png',
            'https://172.16.4.4/a.png',
            'https://192.168.1.10/a.png',
            'https://169.254.169.254/latest/meta-data',
            'https://[::1]/a.png',
            'https://localhost/a.png',
            'http://93.184.216.34/a.png',
            'https://user:pw@93.184.216.34/a.png',
        ] as $url) {
            try {
                $fetcher->fetch($url);
                $this->fail('URL must be rejected: '.$url);
            } catch (GhImageImportException) {
                $this->addToAssertionCount(1);
            }
        }
        Http::assertNothingSent();

        $manager = $this->manager();
        GhServiceCategory::factory()->create(['name_en' => 'Kandura']);
        $file = $this->xlsx([
            ['code', 'name_ar', 'name_en', 'category_name_en', 'image_url'],
            ['K1', 'ك', 'K', 'Kandura', 'https://192.168.0.1/a.png'],
        ]);
        $token = $this->tokenFrom($this->preview($manager, 'products', ['file' => $file]));
        $preview = app(GhImportService::class)->previewFor('products', $token, $manager);
        $this->assertSame(1, $preview->invalid);
        $this->assertSame(0, $preview->create);
    }

    public function test_product_images_attach_from_url_and_zip_and_failures_do_not_block_products(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://93.184.216.34/good.png' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
            'https://93.184.216.34/missing.png' => Http::response('nope', 404),
        ]);
        $manager = $this->manager();
        GhServiceCategory::factory()->create(['name_en' => 'Kandura']);
        $file = $this->xlsx([
            ['code', 'name_ar', 'name_en', 'category_name_en', 'image_url', 'image_filename'],
            ['P-URL', 'أ', 'A', 'Kandura', 'https://93.184.216.34/good.png', ''],
            ['P-ZIP', 'ب', 'B', 'Kandura', '', 'Photo.PNG'],
            ['P-BAD', 'ج', 'C', 'Kandura', 'https://93.184.216.34/missing.png', ''],
            ['P-NOZIP', 'د', 'D', 'Kandura', '', 'absent.jpg'],
        ]);
        $zip = $this->zip(['photos/photo.png' => $this->png(), 'readme.txt' => 'hello']);

        $token = $this->tokenFrom($this->preview($manager, 'products', ['file' => $file, 'zip' => $zip]));
        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'products', 'token' => $token]))
            ->assertSessionHas('warning')
            ->assertSessionHas('gh_import_problems', fn (array $problems) => count($problems) === 2);

        $this->assertSame(4, GhProduct::query()->count());
        $this->assertNotNull(GhProduct::query()->where('code', 'P-URL')->value('image_path'));
        $this->assertNotNull(GhProduct::query()->where('code', 'P-ZIP')->value('image_path'));
        $this->assertNull(GhProduct::query()->where('code', 'P-BAD')->value('image_path'));
        $this->assertNull(GhProduct::query()->where('code', 'P-NOZIP')->value('image_path'));
        Storage::disk('local')->assertExists(GhProduct::query()->where('code', 'P-URL')->value('image_path'));
        $this->assertSame([], glob(storage_path('app/gh-import-tmp/*/*')) ?: []);
    }

    public function test_offering_import_rejects_invalid_tax_class_and_unknown_references(): void
    {
        $manager = $this->manager();
        $product = GhProduct::factory()->create(['code' => 'KAN-001']);
        GhTreatment::factory()->create(['name_en' => 'Wash & Iron']);
        GhTreatment::factory()->create(['name_en' => 'Iron Only']);
        $file = $this->xlsx([
            ['product_code', 'treatment_name_en', 'standard_price', 'tax_class'],
            ['KAN-001', 'Wash & Iron', '12.00', 'STANDARD_RATED'],
            ['KAN-001', 'Iron Only', '6.00', 'MAGIC_RATE'],
            ['KAN-001', 'Nope', '6.00', 'ZERO_RATED'],
            ['KAN-001', 'Iron Only', '-3', 'ZERO_RATED'],
        ]);

        $token = $this->tokenFrom($this->preview($manager, 'offerings', ['file' => $file]));
        $preview = app(GhImportService::class)->previewFor('offerings', $token, $manager);
        $this->assertSame(1, $preview->create);
        $this->assertSame(3, $preview->invalid);

        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'offerings', 'token' => $token]));

        $this->assertSame(1, GhProductOffering::query()->count());
        $this->assertDatabaseHas('gh_product_offerings', ['product_id' => $product->id, 'standard_price' => '12.00', 'tax_class' => 'STANDARD_RATED']);
    }

    public function test_zone_and_customer_imports_create_records_with_address_and_skip_identity_less_rows(): void
    {
        $manager = $this->manager();
        $zones = $this->xlsx([
            ['name_ar', 'name_en', 'min_order_amount', 'min_order_policy'],
            ['مدينة دبي', 'Dubai City', '50', 'VALIDATION_ONLY'],
            ['الشارقة', 'Sharjah', '', ''],
            ['خطأ', 'Bad', '12.345', ''],
        ]);
        $token = $this->tokenFrom($this->preview($manager, 'zones', ['file' => $zones]));
        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'zones', 'token' => $token]));
        $this->assertSame(2, GhZone::query()->count());

        $customers = $this->xlsx([
            ['type', 'name_ar', 'name_en', 'phone', 'email', 'zone_name_en', 'label', 'building', 'is_default'],
            ['B2C', 'أحمد', 'Ahmed', '971501234567', '', 'Dubai City', 'Home', 'Villa 1', '1'],
            ['B2B', 'فندق', 'Hotel', '', 'Laundry@Hotel.example', '', '', '', ''],
            ['B2C', 'بلا هوية', 'No identity', '', '', '', '', '', ''],
            ['B2C', 'مكرر', 'Dup', '971501234567', '', '', '', '', ''],
        ]);
        $token = $this->tokenFrom($this->preview($manager, 'customers', ['file' => $customers]));
        $preview = app(GhImportService::class)->previewFor('customers', $token, $manager);
        $this->assertSame(2, $preview->create);
        $this->assertSame(1, $preview->invalid);
        $this->assertSame(1, $preview->duplicate);

        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'customers', 'token' => $token]));

        $ahmed = GhCustomer::query()->where('phone', '971501234567')->firstOrFail();
        $this->assertSame(1, $ahmed->addresses()->where('is_default', true)->count());
        $this->assertSame('laundry@hotel.example', GhCustomer::query()->where('type', GhCustomerType::B2B)->value('email'));
    }

    public function test_contract_import_groups_rows_by_reference_and_rejects_invalid_groups_atomically(): void
    {
        $manager = $this->manager();
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2B, 'phone' => '971044445555']);
        $offeringA = GhProductOffering::factory()->create();
        $offeringB = GhProductOffering::factory()->create();
        $row = fn (string $ref, GhProductOffering $o, string $price) => [
            $ref, '971044445555', '', '2027-01-01', '2027-12-31', '', '', $o->product->code, $o->treatment->name_en, $price, '',
        ];
        $file = $this->xlsx([
            ['reference', 'customer_phone', 'customer_email', 'effective_from', 'effective_to', 'status', 'notes', 'product_code', 'treatment_name_en', 'contract_price', 'offering_status'],
            $row('ctr-1', $offeringA, '9.50'),
            $row('ctr-1', $offeringB, '7'),
            $row('CTR-2', $offeringA, '5'),
            $row('CTR-2', $offeringB, 'abc'),
        ]);

        $token = $this->tokenFrom($this->preview($manager, 'contracts', ['file' => $file]));
        $preview = app(GhImportService::class)->previewFor('contracts', $token, $manager);
        $this->assertSame(2, $preview->total);
        $this->assertSame(1, $preview->create);
        $this->assertSame(1, $preview->invalid);

        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'contracts', 'token' => $token]));

        $contract = GhB2bContract::query()->where('reference', 'CTR-1')->firstOrFail();
        $this->assertSame($customer->id, $contract->customer_id);
        $this->assertSame('DRAFT', $contract->status);
        $this->assertSame(2, $contract->contractOfferings()->count());
        $this->assertSame(0, GhB2bContract::query()->where('reference', 'CTR-2')->count());
    }

    private function uploadTemplate(User $manager, string $entity): UploadedFile
    {
        $response = $this->actingAs($manager)->get(route('gh.import.template', ['entity' => $entity]))->assertOk();
        $copy = tempnam(sys_get_temp_dir(), 'ght').'.xlsx';
        copy($response->baseResponse->getFile()->getPathname(), $copy);
        $this->tempFiles[] = $copy;

        return new UploadedFile($copy, 'template.xlsx', GhSpreadsheetSupport::XLSX_MIME, null, true);
    }

    public function test_product_template_round_trips_with_live_categories(): void
    {
        $manager = $this->manager();
        GhServiceCategory::factory()->create(['name_en' => 'Thobes & Co', 'status' => 'ACTIVE']);
        GhServiceCategory::factory()->create(['name_en' => 'Rugs', 'status' => 'ACTIVE']);

        $token = $this->tokenFrom($this->preview($manager, 'products', ['file' => $this->uploadTemplate($manager, 'products')]));
        $preview = app(GhImportService::class)->previewFor('products', $token, $manager);

        $this->assertSame(6, $preview->total);
        $this->assertSame(6, $preview->create);
        $this->assertSame(0, $preview->invalid);
        $this->assertSame([], $preview->errors);

        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'products', 'token' => $token]));
        $this->assertSame(6, GhProduct::query()->count());
    }

    public function test_product_template_without_categories_falls_back_and_says_import_categories_first(): void
    {
        $manager = $this->manager();
        $book = IOFactory::load($this->uploadTemplate($manager, 'products')->getRealPath());

        $this->assertSame('Kandura', $book->getSheet(0)->getCell('D2')->getValue());
        $instructions = collect($book->getSheet(1)->toArray())->flatten()->implode("\n");
        $this->assertStringContainsString(trans('gh.import.sheet.import_categories_first'), $instructions);
        $this->assertStringContainsString(trans('gh.import.sheet.examples_fallback'), $instructions);
    }

    public function test_offering_template_uses_live_products_and_treatments(): void
    {
        $manager = $this->manager();
        $product = GhProduct::factory()->create(['code' => 'LIVE-1', 'status' => 'ACTIVE']);
        $treatment = GhTreatment::factory()->create(['name_en' => 'Live Wash', 'status' => 'ACTIVE']);

        $token = $this->tokenFrom($this->preview($manager, 'offerings', ['file' => $this->uploadTemplate($manager, 'offerings')]));
        $preview = app(GhImportService::class)->previewFor('offerings', $token, $manager);

        $this->assertSame(1, $preview->create);
        $this->assertSame(0, $preview->invalid);
        $this->assertSame('LIVE-1', $preview->rows[0]['payload']['product_code']);
        $this->assertSame($treatment->id, $preview->rows[0]['payload']['treatment_id']);
        $this->assertSame($product->id, $preview->rows[0]['payload']['product_id']);
    }

    public function test_contract_template_uses_live_data_and_round_trips_with_auto_references(): void
    {
        $manager = $this->manager();
        GhCustomer::factory()->create(['type' => GhCustomerType::B2B, 'phone' => '971055550001']);
        GhProductOffering::factory()->count(3)->create();

        $token = $this->tokenFrom($this->preview($manager, 'contracts', ['file' => $this->uploadTemplate($manager, 'contracts')]));
        $preview = app(GhImportService::class)->previewFor('contracts', $token, $manager);

        $this->assertSame([], $preview->errors);
        $this->assertSame(0, $preview->invalid);
        $this->assertGreaterThanOrEqual(1, $preview->create);
        $this->assertNull($preview->rows[0]['payload']['reference']);

        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'contracts', 'token' => $token]));
        $this->assertSame($preview->create, GhB2bContract::query()->count());
        GhB2bContract::query()->pluck('reference')->each(fn ($ref) => $this->assertMatchesRegularExpression('/^GH-CTR-\d{6}$/', $ref));
    }

    public function test_contract_import_blank_reference_groups_by_import_group_and_consecutive_rows(): void
    {
        $manager = $this->manager();
        $hotel = GhCustomer::factory()->create(['type' => GhCustomerType::B2B, 'phone' => '971044445555']);
        $cafe = GhCustomer::factory()->create(['type' => GhCustomerType::B2B, 'phone' => '971044446666']);
        $a = GhProductOffering::factory()->create();
        $b = GhProductOffering::factory()->create();
        $existing = GhB2bContract::factory()->create(['customer_id' => $cafe->id, 'reference' => 'OLD-1']);
        $header = ['reference', 'import_group', 'customer_phone', 'customer_email', 'effective_from', 'effective_to', 'status', 'notes', 'product_code', 'treatment_name_en', 'contract_price', 'offering_status'];
        $row = fn (string $ref, string $group, string $phone, string $from, GhProductOffering $o, string $price) => [
            $ref, $group, $phone, '', $from, '', '', '', $o->product->code, $o->treatment->name_en, $price, '',
        ];
        $file = $this->xlsx([
            $header,
            $row('', 'G1', '971044445555', '2031-01-01', $a, '5'),
            $row('', 'G1', '971044445555', '2031-01-01', $b, '6'),
            $row('', '', '971044446666', '2032-01-01', $a, '7'),
            $row('', '', '971044446666', '2032-01-01', $b, '8'),
            $row('NEW-REF-9', '', '971044445555', '2033-01-01', $a, '9'),
            $row($existing->reference, '', '971044446666', '2034-01-01', $a, '10'),
        ]);

        $token = $this->tokenFrom($this->preview($manager, 'contracts', ['file' => $file]));
        $preview = app(GhImportService::class)->previewFor('contracts', $token, $manager);
        $this->assertSame(4, $preview->total);
        $this->assertSame(3, $preview->create);
        $this->assertSame(1, $preview->duplicate);

        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'contracts', 'token' => $token]));

        $this->assertSame(1 + 3, GhB2bContract::query()->count());
        $this->assertSame(2, GhB2bContract::query()->where('customer_id', $hotel->id)->whereDate('effective_from', '2031-01-01')->firstOrFail()->contractOfferings()->count());
        $this->assertSame(2, GhB2bContract::query()->where('customer_id', $cafe->id)->whereDate('effective_from', '2032-01-01')->firstOrFail()->contractOfferings()->count());
        $this->assertSame(1, GhB2bContract::query()->where('reference', 'NEW-REF-9')->count());
        $auto = GhB2bContract::query()->whereDate('effective_from', '>=', '2031-01-01')->whereDate('effective_from', '<=', '2032-01-01')->pluck('reference');
        $this->assertCount(2, $auto->unique());
        $auto->each(fn ($ref) => $this->assertMatchesRegularExpression('/^GH-CTR-\d{6}$/', $ref));
    }

    public function test_contract_import_reupload_of_auto_group_is_skipped_as_duplicate(): void
    {
        $manager = $this->manager();
        GhCustomer::factory()->create(['type' => GhCustomerType::B2B, 'phone' => '971044445555']);
        $o = GhProductOffering::factory()->create();
        $make = fn () => $this->xlsx([
            ['import_group', 'customer_phone', 'customer_email', 'effective_from', 'product_code', 'treatment_name_en', 'contract_price'],
            ['G1', '971044445555', '', '2035-01-01', $o->product->code, $o->treatment->name_en, '5'],
        ]);

        $token = $this->tokenFrom($this->preview($manager, 'contracts', ['file' => $make()]));
        $this->actingAs($manager)->post(route('gh.import.confirm', ['entity' => 'contracts', 'token' => $token]));
        $this->assertSame(1, GhB2bContract::query()->count());

        $token = $this->tokenFrom($this->preview($manager, 'contracts', ['file' => $make()]));
        $preview = app(GhImportService::class)->previewFor('contracts', $token, $manager);
        $this->assertSame(0, $preview->create);
        $this->assertSame(1, $preview->duplicate);
    }

    public function test_row_limit_and_error_download(): void
    {
        $manager = $this->manager();
        $rows = [['name_ar', 'name_en']];
        for ($i = 0; $i < 501; $i++) {
            $rows[] = ['اسم '.$i, 'Name '.$i];
        }
        $this->preview($manager, 'treatments', ['file' => $this->xlsx($rows)])
            ->assertSessionHasErrorsIn('ghImport', 'file');

        $bad = $this->xlsx([['name_ar', 'name_en'], ['', 'NoArabic']]);
        $token = $this->tokenFrom($this->preview($manager, 'treatments', ['file' => $bad]));
        $this->actingAs($manager)->get(route('gh.import.errors', ['entity' => 'treatments', 'token' => $token]))->assertOk();
    }
}
