<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validation messages must render in natural AR/EN with friendly attribute
 * names — never raw "qualifications.1.degree"-style English.
 */
class ValidationLangTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
    }

    /**
     * Row 0 is valid; row 1 has a specialization but no degree; and name_ar
     * is missing — one request exercises custom + attribute messages.
     *
     * @return array<string, mixed>
     */
    private function invalidPayload(): array
    {
        return [
            'name_ar' => '',
            'name_en' => 'Test Employee',
            'email' => 'validation@hrpro.test',
            'password' => 'password123',
            'role' => 'EMPLOYEE',
            'currency' => 'EGP',
            'language_preference' => 'ar',
            'shift_type_default' => 'FULL_TIME',
            'status' => 'ACTIVE',
            'base_salary' => 0,
            'profile' => ['employee_code' => 'EMP-V1'],
            'contract' => [
                'hire_date' => '2024-01-01', 'contract_start' => '2024-01-01',
                'contract_end' => '2026-01-01', 'contract_type' => 'سنة',
            ],
            'qualifications' => [
                ['degree' => 'BSc', 'specialization' => 'CS', 'graduation_year' => 2016],
                ['degree' => '', 'specialization' => 'Math', 'graduation_year' => ''],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function flashedErrorsFor(string $key): array
    {
        return session('errors')->getBag('default')->get($key);
    }

    public function test_arabic_custom_message_for_partial_qualification_row(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->invalidPayload())
            ->assertSessionHasErrors(['qualifications.1.degree', 'name_ar']);

        $message = $this->flashedErrorsFor('qualifications.1.degree')[0];
        $this->assertSame('أدخل الشهادة أو احذف هذا السطر.', $message);
        $this->assertStringNotContainsString('qualifications.1.degree', $message);

        $this->assertSame('حقل الاسم (بالعربية) مطلوب.', $this->flashedErrorsFor('name_ar')[0]);
    }

    public function test_english_custom_message_for_partial_qualification_row(): void
    {
        $this->withSession(['locale' => 'en'])
            ->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->invalidPayload())
            ->assertSessionHasErrors(['qualifications.1.degree', 'name_ar']);

        $message = $this->flashedErrorsFor('qualifications.1.degree')[0];
        $this->assertSame('Enter the degree or remove this row.', $message);
        $this->assertStringNotContainsString('qualifications.1.degree', $message);

        $this->assertSame('The name (Arabic) field is required.', $this->flashedErrorsFor('name_ar')[0]);
    }

    public function test_bad_credentials_message_is_translated(): void
    {
        $employee = User::factory()->employee()->create();

        $this->withSession(['locale' => 'ar'])
            ->post(route('login.store'), [
                'email' => $employee->email,
                'password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(
            'بيانات الاعتماد هذه لا تتطابق مع سجلاتنا.',
            $this->flashedErrorsFor('email')[0],
        );

        $this->withSession(['locale' => 'en'])
            ->post(route('login.store'), [
                'email' => $employee->email,
                'password' => 'wrong-password',
            ]);

        $this->assertSame(
            'These credentials do not match our records.',
            $this->flashedErrorsFor('email')[0],
        );
    }
}
