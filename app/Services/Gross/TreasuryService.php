<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSupplier;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\Gross\GrossTreasuryVoucherSequence;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TreasuryService
{
    public function __construct(
        private readonly TreasuryVoucherAttachmentService $attachments,
    ) {}

    /**
     * @return Collection<int, array{account: GrossTreasuryAccount, balance: string}>
     */
    public function accountsWithBalances(): Collection
    {
        return GrossTreasuryAccount::query()
            ->orderBy('kind')
            ->orderBy('id')
            ->get()
            ->map(fn (GrossTreasuryAccount $account) => [
                'account' => $account,
                'balance' => $this->money($this->balanceOf($account)),
            ]);
    }

    public function paginateVouchers(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossTreasuryVoucher::query()
            ->with(['account', 'supplier', 'expenseType', 'salesInvoice', 'shippingCompany', 'creator'])
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        if (! empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }
        if (! empty($filters['purpose'])) {
            $q->where('purpose', $filters['purpose']);
        }
        if (! empty($filters['account_id'])) {
            $q->where('account_id', (int) $filters['account_id']);
        }
        if (! empty($filters['supplier_id'])) {
            $q->where('supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['expense_type_id'])) {
            $q->where('expense_type_id', (int) $filters['expense_type_id']);
        }

        return $q->paginate(min($perPage, 50))->withQueryString();
    }

    public function activeAccounts(): Collection
    {
        return GrossTreasuryAccount::query()
            ->where('is_active', true)
            ->orderBy('kind')
            ->get();
    }

    /**
     * @param  array{
     *     purpose: string,
     *     account_id: int,
     *     amount: float|string,
     *     voucher_date: string,
     *     notes?: string|null,
     *     supplier_id?: int|null,
     *     expense_type_id?: int|null
     * }  $data
     */
    public function createPayment(User $actor, array $data, array $files = []): GrossTreasuryVoucher
    {
        return $this->createVoucher($actor, GrossTreasuryVoucher::TYPE_PAYMENT, $data, $files);
    }

    /**
     * @param  array{
     *     purpose: string,
     *     account_id: int,
     *     amount: float|string,
     *     voucher_date: string,
     *     notes?: string|null,
     *     supplier_id?: int|null,
     *     expense_type_id?: int|null
     * }  $data
     */
    public function createReceipt(User $actor, array $data, array $files = []): GrossTreasuryVoucher
    {
        return $this->createVoucher($actor, GrossTreasuryVoucher::TYPE_RECEIPT, $data, $files);
    }

    /**
     * @param  array{account_id: int, sales_invoice_id: int, voucher_date?: string, notes?: string|null}  $data
     */
    public function createCourierCodReceipt(User $actor, array $data, array $files = []): GrossTreasuryVoucher
    {
        return DB::transaction(function () use ($actor, $data, $files) {
            $invoiceId = (int) ($data['sales_invoice_id'] ?? 0);
            if ($invoiceId < 1) {
                throw GrossException::courierCodInvoiceRequired();
            }

            if ($this->courierCodReceiptForInvoice($invoiceId) !== null) {
                throw GrossException::courierCodAlreadyPosted();
            }

            /** @var GrossSalesInvoice $invoice */
            $invoice = GrossSalesInvoice::query()->whereKey($invoiceId)->lockForUpdate()->firstOrFail();

            if ($invoice->cod_collection_status !== GrossSalesInvoice::COD_COLLECTED) {
                throw GrossException::courierCodNotCollected();
            }

            if ($invoice->shipping_company_id === null) {
                throw GrossException::courierCodInvoiceRequired();
            }

            $amount = round($invoice->courierMerchandiseAmount(), 2);
            if ($amount <= 0) {
                throw GrossException::invalidAmount();
            }

            if (abs(round((float) ($invoice->cod_collected_amount ?? 0), 2) - $amount) > 0.009) {
                $invoice->update(['cod_collected_amount' => $amount]);
            }

            return $this->createVoucher($actor, GrossTreasuryVoucher::TYPE_RECEIPT, [
                'purpose' => GrossTreasuryVoucher::PURPOSE_COURIER_COD,
                'account_id' => (int) $data['account_id'],
                'amount' => $amount,
                'voucher_date' => $data['voucher_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'sales_invoice_id' => $invoice->id,
                'shipping_company_id' => $invoice->shipping_company_id,
            ], $files);
        });
    }

    public function courierCodReceiptForInvoice(int $invoiceId): ?GrossTreasuryVoucher
    {
        return GrossTreasuryVoucher::query()
            ->where('purpose', GrossTreasuryVoucher::PURPOSE_COURIER_COD)
            ->where('sales_invoice_id', $invoiceId)
            ->first();
    }

    public function balanceOf(GrossTreasuryAccount $account): float
    {
        $receipts = (float) GrossTreasuryVoucher::query()
            ->where('account_id', $account->id)
            ->where('type', GrossTreasuryVoucher::TYPE_RECEIPT)
            ->sum('amount');
        $payments = (float) GrossTreasuryVoucher::query()
            ->where('account_id', $account->id)
            ->where('type', GrossTreasuryVoucher::TYPE_PAYMENT)
            ->sum('amount');

        return round((float) $account->opening_balance + $receipts - $payments, 2);
    }

    /**
     * @param  array{
     *     purpose: string,
     *     account_id: int,
     *     amount: float|string,
     *     voucher_date: string,
     *     notes?: string|null,
     *     supplier_id?: int|null,
     *     expense_type_id?: int|null,
     *     sales_invoice_id?: int|null,
     *     shipping_company_id?: int|null
     * }  $data
     */
    private function createVoucher(User $actor, string $type, array $data, array $files = []): GrossTreasuryVoucher
    {
        return DB::transaction(function () use ($actor, $type, $data, $files) {
            /** @var GrossTreasuryAccount $account */
            $account = GrossTreasuryAccount::query()
                ->whereKey((int) $data['account_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! $account->isActive()) {
                throw GrossException::inactiveTreasuryAccount();
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw GrossException::invalidAmount();
            }

            if ($type === GrossTreasuryVoucher::TYPE_PAYMENT && $this->balanceOf($account) < $amount) {
                throw GrossException::insufficientTreasury();
            }

            [$purpose, $supplierId, $expenseTypeId, $salesInvoiceId, $shippingCompanyId] = $this->resolvePurpose($data, $type);

            $voucher = GrossTreasuryVoucher::query()->create([
                'voucher_number' => $this->nextNumber($type),
                'type' => $type,
                'purpose' => $purpose,
                'account_id' => $account->id,
                'supplier_id' => $supplierId,
                'expense_type_id' => $expenseTypeId,
                'sales_invoice_id' => $salesInvoiceId,
                'shipping_company_id' => $shippingCompanyId,
                'currency' => $account->currency,
                'amount' => $amount,
                'voucher_date' => $data['voucher_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            if ($files !== []) {
                $this->attachments->storeMany($voucher, $files, $actor);
            }

            return $voucher;
        });
    }

    /**
     * @param  array{purpose: string, supplier_id?: int|null, expense_type_id?: int|null, sales_invoice_id?: int|null, shipping_company_id?: int|null, amount?: float|string}  $data
     * @return array{0: string, 1: int|null, 2: int|null, 3: int|null, 4: int|null}
     */
    private function resolvePurpose(array $data, string $type): array
    {
        $purpose = strtoupper((string) ($data['purpose'] ?? ''));
        if (! in_array($purpose, [
            GrossTreasuryVoucher::PURPOSE_SUPPLIER,
            GrossTreasuryVoucher::PURPOSE_EXPENSE,
            GrossTreasuryVoucher::PURPOSE_COURIER_COD,
            GrossTreasuryVoucher::PURPOSE_OTHER,
        ], true)) {
            throw GrossException::invalidTransition();
        }

        if ($purpose === GrossTreasuryVoucher::PURPOSE_OTHER) {
            if ($type !== GrossTreasuryVoucher::TYPE_RECEIPT) {
                throw GrossException::invalidTransition();
            }

            return [$purpose, null, null, null, null];
        }

        if ($purpose === GrossTreasuryVoucher::PURPOSE_COURIER_COD) {
            $invoiceId = (int) ($data['sales_invoice_id'] ?? 0);
            $shippingCompanyId = (int) ($data['shipping_company_id'] ?? 0);
            if ($invoiceId < 1 || $shippingCompanyId < 1) {
                throw GrossException::courierCodInvoiceRequired();
            }

            if ($this->courierCodReceiptForInvoice($invoiceId) !== null) {
                throw GrossException::courierCodAlreadyPosted();
            }

            /** @var GrossSalesInvoice|null $invoice */
            $invoice = GrossSalesInvoice::query()->whereKey($invoiceId)->first();
            if ($invoice === null
                || $invoice->cod_collection_status !== GrossSalesInvoice::COD_COLLECTED
                || (int) $invoice->shipping_company_id !== $shippingCompanyId) {
                throw GrossException::courierCodNotCollected();
            }

            $expected = round($invoice->courierMerchandiseAmount(), 2);
            $submitted = round((float) ($data['amount'] ?? 0), 2);
            if ($expected <= 0 || abs($expected - $submitted) > 0.009) {
                throw GrossException::courierCodAmountMismatch();
            }

            if (abs(round((float) ($invoice->cod_collected_amount ?? 0), 2) - $expected) > 0.009) {
                $invoice->update(['cod_collected_amount' => $expected]);
            }

            return [$purpose, null, null, $invoiceId, $shippingCompanyId];
        }

        if ($purpose === GrossTreasuryVoucher::PURPOSE_SUPPLIER) {
            $supplierId = (int) ($data['supplier_id'] ?? 0);
            if ($supplierId < 1 || ! GrossSupplier::query()->whereKey($supplierId)->exists()) {
                throw GrossException::treasurySupplierRequired();
            }

            return [$purpose, $supplierId, null, null, null];
        }

        $expenseTypeId = (int) ($data['expense_type_id'] ?? 0);
        /** @var GrossExpenseType|null $expenseType */
        $expenseType = GrossExpenseType::query()->whereKey($expenseTypeId)->first();
        if ($expenseType === null) {
            throw GrossException::treasuryExpenseTypeRequired();
        }
        if (! $expenseType->isActive()) {
            throw GrossException::inactiveExpenseType();
        }

        return [$purpose, null, $expenseType->id, null, null];
    }

    private function nextNumber(string $type): string
    {
        $yearMonth = now()->format('Ym');
        $prefix = $type === GrossTreasuryVoucher::TYPE_PAYMENT ? 'PV' : 'RV';

        $seq = GrossTreasuryVoucherSequence::query()
            ->where('year_month', $yearMonth)
            ->where('kind', $type)
            ->lockForUpdate()
            ->first();

        if ($seq === null) {
            $seq = GrossTreasuryVoucherSequence::query()->create([
                'year_month' => $yearMonth,
                'kind' => $type,
                'last_number' => 0,
            ]);
            $seq = GrossTreasuryVoucherSequence::query()
                ->whereKey($seq->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $seq->last_number = (int) $seq->last_number + 1;
        $seq->save();

        return sprintf('%s-%s-%04d', $prefix, $yearMonth, $seq->last_number);
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
