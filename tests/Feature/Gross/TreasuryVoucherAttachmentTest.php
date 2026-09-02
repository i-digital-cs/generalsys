<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\Gross\GrossTreasuryVoucherAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TreasuryVoucherAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_can_store_and_download_attachments(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $electricity = GrossExpenseType::query()->where('name_en', 'Electricity')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_EXPENSE,
                'expense_type_id' => $electricity->id,
                'account_id' => $cash->id,
                'amount' => 300,
                'voucher_date' => now()->toDateString(),
                'attachments' => [
                    UploadedFile::fake()->create('bank-transfer.pdf', 120, 'application/pdf'),
                    UploadedFile::fake()->image('receipt.jpg'),
                ],
            ])
            ->assertRedirect();

        $voucher = GrossTreasuryVoucher::query()->latest('id')->firstOrFail();
        $this->assertCount(2, $voucher->attachments);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.treasury.show', $voucher))
            ->assertOk()
            ->assertSee('Attachments', false)
            ->assertSee('bank-transfer.pdf', false)
            ->assertSee('receipt.jpg', false);

        $attachment = $voucher->attachments()->where('original_name', 'bank-transfer.pdf')->firstOrFail();
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->actingAs($manager)
            ->get(route('gross.treasury.attachments.download', [$voucher, $attachment]))
            ->assertOk();
    }

    public function test_invalid_attachment_mime_rejected(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $electricity = GrossExpenseType::query()->where('name_en', 'Electricity')->firstOrFail();

        $this->actingAs($manager)
            ->from(route('gross.treasury.create', ['type' => 'PAYMENT']))
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_PAYMENT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_EXPENSE,
                'expense_type_id' => $electricity->id,
                'account_id' => $cash->id,
                'amount' => 50,
                'voucher_date' => now()->toDateString(),
                'attachments' => [
                    UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('attachments.0');

        $this->assertSame(0, GrossTreasuryVoucherAttachment::query()->count());
    }

    public function test_too_many_attachments_rejected(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $electricity = GrossExpenseType::query()->where('name_en', 'Electricity')->firstOrFail();

        $files = [];
        for ($i = 0; $i < 6; $i++) {
            $files[] = UploadedFile::fake()->image("proof-{$i}.jpg");
        }

        $this->actingAs($manager)
            ->from(route('gross.treasury.create', ['type' => 'PAYMENT']))
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_PAYMENT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_EXPENSE,
                'expense_type_id' => $electricity->id,
                'account_id' => $cash->id,
                'amount' => 50,
                'voucher_date' => now()->toDateString(),
                'attachments' => $files,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('attachments');

        $this->assertSame(0, GrossTreasuryVoucher::query()->count());
    }

    public function test_attachment_download_forbidden_for_sales_user(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $electricity = GrossExpenseType::query()->where('name_en', 'Electricity')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_EXPENSE,
                'expense_type_id' => $electricity->id,
                'account_id' => $cash->id,
                'amount' => 100,
                'voucher_date' => now()->toDateString(),
                'attachments' => [
                    UploadedFile::fake()->image('proof.jpg'),
                ],
            ])
            ->assertRedirect();

        $voucher = GrossTreasuryVoucher::query()->latest('id')->firstOrFail();
        $attachment = $voucher->attachments()->firstOrFail();

        $this->actingAs($sales->fresh())
            ->get(route('gross.treasury.attachments.download', [$voucher, $attachment]))
            ->assertForbidden();
    }
}
