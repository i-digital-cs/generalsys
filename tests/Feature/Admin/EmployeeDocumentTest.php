<?php

namespace Tests\Feature\Admin;

use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->manager = User::factory()->manager()->create();
        $this->employee = User::factory()->employee()->create();
        $this->employee->profile()->create(['employee_code' => 'EMP-D1']);
    }

    private function pdf(string $name = 'doc.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, '%PDF-1.4 fake pdf content');
    }

    public function test_manager_uploads_a_valid_document(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.documents.store', $this->employee), [
                'type' => 'ID_CARD',
                'documents' => [$this->pdf('national-id.pdf')],
            ])
            ->assertRedirect(route('admin.employees.edit', $this->employee));

        $document = $this->employee->documents()->firstOrFail();

        $this->assertSame('ID_CARD', $document->type);
        $this->assertNotSame('national-id.pdf', basename($document->file_path));
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_batch_upload_stores_every_file_in_one_request(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.documents.store', $this->employee), [
                'type' => 'QUALIFICATION',
                'documents' => [$this->pdf('a.pdf'), $this->pdf('b.pdf'), $this->pdf('c.pdf')],
            ])
            ->assertRedirect(route('admin.employees.edit', $this->employee));

        $this->assertSame(3, $this->employee->documents()->count());
        $this->assertSame(3, $this->employee->documents()->where('type', 'QUALIFICATION')->count());
    }

    public function test_one_bad_file_rejects_the_whole_batch(): void
    {
        $evil = UploadedFile::fake()->createWithContent('evil.php', '<?php echo "x";');

        $this->actingAs($this->manager)
            ->post(route('admin.employees.documents.store', $this->employee), [
                'type' => 'OTHER',
                'documents' => [$this->pdf('good.pdf'), $evil, $this->pdf('good2.pdf')],
            ])
            ->assertSessionHasErrors('documents.1');

        $this->assertSame(0, $this->employee->documents()->count());
    }

    public function test_upload_rejects_disallowed_mime_type(): void
    {
        $file = UploadedFile::fake()->createWithContent('evil.php', '<?php echo "x";');

        $this->actingAs($this->manager)
            ->post(route('admin.employees.documents.store', $this->employee), [
                'type' => 'OTHER',
                'documents' => [$file],
            ])
            ->assertSessionHasErrors('documents.0');

        $this->assertSame(0, $this->employee->documents()->count());
    }

    public function test_upload_rejects_oversize_file(): void
    {
        $file = UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf');

        $this->actingAs($this->manager)
            ->post(route('admin.employees.documents.store', $this->employee), [
                'type' => 'CONTRACT_PDF',
                'documents' => [$file],
            ])
            ->assertSessionHasErrors('documents.0');
    }

    public function test_upload_rejects_unknown_document_type(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.documents.store', $this->employee), [
                'type' => 'PASSPORT',
                'documents' => [$this->pdf('scan.pdf')],
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_employee_cannot_upload_documents(): void
    {
        $this->actingAs($this->employee)
            ->post(route('admin.employees.documents.store', $this->employee), [
                'type' => 'OTHER',
                'documents' => [$this->pdf('scan.pdf')],
            ])
            ->assertForbidden();
    }

    private function makeDocument(): EmployeeDocument
    {
        Storage::disk('local')->put("employee-documents/{$this->employee->id}/stored.pdf", 'content');

        return $this->employee->documents()->create([
            'type' => 'ID_CARD',
            'file_path' => "employee-documents/{$this->employee->id}/stored.pdf",
            'uploaded_at' => now(),
        ]);
    }

    public function test_manager_can_download_document(): void
    {
        $document = $this->makeDocument();

        $this->actingAs($this->manager)
            ->get(route('documents.download', $document))
            ->assertOk();
    }

    public function test_owning_employee_can_download_own_document(): void
    {
        $document = $this->makeDocument();

        $this->actingAs($this->employee)
            ->get(route('documents.download', $document))
            ->assertOk();
    }

    public function test_other_employee_gets_403_on_download(): void
    {
        $document = $this->makeDocument();
        $stranger = User::factory()->employee()->create();

        $this->actingAs($stranger)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_team_leader_gets_403_on_download(): void
    {
        $document = $this->makeDocument();
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('documents.download', $document))
            ->assertForbidden();
    }

    public function test_manager_deletes_document_removing_file_and_row(): void
    {
        $document = $this->makeDocument();

        $this->actingAs($this->manager)
            ->delete(route('admin.employees.documents.destroy', [$this->employee, $document]))
            ->assertRedirect(route('admin.employees.edit', $this->employee));

        $this->assertSame(0, $this->employee->documents()->count());
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_cannot_delete_another_employees_document_via_url(): void
    {
        $document = $this->makeDocument();
        $stranger = User::factory()->employee()->create();

        // The document belongs to $this->employee, the URL names the stranger.
        $this->actingAs($this->manager)
            ->delete(route('admin.employees.documents.destroy', [$stranger, $document]))
            ->assertNotFound();

        $this->assertSame(1, $this->employee->documents()->count());
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_team_leader_and_employee_get_403_on_document_delete(): void
    {
        $document = $this->makeDocument();
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->delete(route('admin.employees.documents.destroy', [$this->employee, $document]))
            ->assertForbidden();

        $this->actingAs($this->employee)
            ->delete(route('admin.employees.documents.destroy', [$this->employee, $document]))
            ->assertForbidden();

        $this->assertSame(1, $this->employee->documents()->count());
    }

    public function test_guest_is_redirected_from_download(): void
    {
        $document = $this->makeDocument();

        $this->get(route('documents.download', $document))
            ->assertRedirect('/login');
    }
}
