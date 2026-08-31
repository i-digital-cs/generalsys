<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossExpenseType;
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
            ->with(['account', 'supplier', 'expenseType', 'creator'])
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
    public function createPayment(User $actor, array $data): GrossTreasuryVoucher
    {
        return $this->createVoucher($actor, GrossTreasuryVoucher::TYPE_PAYMENT, $data);
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
    public function createReceipt(User $actor, array $data): GrossTreasuryVoucher
    {
        return $this->createVoucher($actor, GrossTreasuryVoucher::TYPE_RECEIPT, $data);
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
     *     expense_type_id?: int|null
     * }  $data
     */
    private function createVoucher(User $actor, string $type, array $data): GrossTreasuryVoucher
    {
        return DB::transaction(function () use ($actor, $type, $data) {
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

            [$purpose, $supplierId, $expenseTypeId] = $this->resolvePurpose($data);

            return GrossTreasuryVoucher::query()->create([
                'voucher_number' => $this->nextNumber($type),
                'type' => $type,
                'purpose' => $purpose,
                'account_id' => $account->id,
                'supplier_id' => $supplierId,
                'expense_type_id' => $expenseTypeId,
                'currency' => $account->currency,
                'amount' => $amount,
                'voucher_date' => $data['voucher_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * @param  array{purpose: string, supplier_id?: int|null, expense_type_id?: int|null}  $data
     * @return array{0: string, 1: int|null, 2: int|null}
     */
    private function resolvePurpose(array $data): array
    {
        $purpose = strtoupper((string) ($data['purpose'] ?? ''));
        if (! in_array($purpose, [
            GrossTreasuryVoucher::PURPOSE_SUPPLIER,
            GrossTreasuryVoucher::PURPOSE_EXPENSE,
        ], true)) {
            throw GrossException::invalidTransition();
        }

        if ($purpose === GrossTreasuryVoucher::PURPOSE_SUPPLIER) {
            $supplierId = (int) ($data['supplier_id'] ?? 0);
            if ($supplierId < 1 || ! GrossSupplier::query()->whereKey($supplierId)->exists()) {
                throw GrossException::treasurySupplierRequired();
            }

            return [$purpose, $supplierId, null];
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

        return [$purpose, null, $expenseType->id];
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
