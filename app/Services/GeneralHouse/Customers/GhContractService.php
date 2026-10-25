<?php

namespace App\Services\GeneralHouse\Customers;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhB2bContractOffering;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhProductOffering;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhContractStatus;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhContractService
{
    /**
     * @param  array{q?: string, status?: string, customer_id?: int|string}  $filters
     * @return LengthAwarePaginator<int, GhB2bContract>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));
        $q = GhB2bContract::query()->with('customer')->orderByDesc('effective_from')->orderByDesc('id');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('reference', 'like', $like)
                    ->orWhereHas('customer', function ($c) use ($like) {
                        $c->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like);
                    });
            });
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, GhContractStatus::ALL, true)) {
            $q->where('status', $status);
        }

        if (($filters['customer_id'] ?? '') !== '' && $filters['customer_id'] !== null) {
            $q->where('customer_id', (int) $filters['customer_id']);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{customer_id: int, reference: string, effective_from: string, effective_to?: ?string, status?: string, notes?: ?string}  $data
     */
    public function create(array $data): GhB2bContract
    {
        $customer = $this->assertB2bCustomer((int) $data['customer_id']);
        [$from, $to] = $this->normalizeDates($data['effective_from'], $data['effective_to'] ?? null);
        $status = $data['status'] ?? GhContractStatus::DRAFT;
        $this->assertStatus($status);
        $this->assertNoOverlap($customer->id, $from, $to, $status);

        return GhB2bContract::query()->create([
            'customer_id' => $customer->id,
            'reference' => strtoupper(trim($data['reference'])),
            'effective_from' => $from->toDateString(),
            'effective_to' => $to?->toDateString(),
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * @param  array{customer_id: int, reference: string, effective_from: string, effective_to?: ?string, status?: string, notes?: ?string}  $data
     */
    public function update(GhB2bContract $contract, array $data): GhB2bContract
    {
        $customer = $this->assertB2bCustomer((int) $data['customer_id']);
        [$from, $to] = $this->normalizeDates($data['effective_from'], $data['effective_to'] ?? null);
        $status = $data['status'] ?? $contract->status;
        $this->assertStatus($status);
        $this->assertNoOverlap($customer->id, $from, $to, $status, $contract->id);

        $contract->update([
            'customer_id' => $customer->id,
            'reference' => strtoupper(trim($data['reference'])),
            'effective_from' => $from->toDateString(),
            'effective_to' => $to?->toDateString(),
            'status' => $status,
            'notes' => $data['notes'] ?? null,
        ]);

        return $contract->refresh();
    }

    public function delete(GhB2bContract $contract): void
    {
        if ($contract->contractOfferings()->exists()) {
            throw ValidationException::withMessages([
                'contract' => [__('gh.errors.contract_has_offerings')],
            ]);
        }

        $contract->delete();
    }

    /**
     * @param  array{offering_id: int, contract_price: mixed, currency?: string, status?: string}  $data
     */
    public function addOffering(GhB2bContract $contract, array $data): GhB2bContractOffering
    {
        $offeringId = (int) $data['offering_id'];
        if (! GhProductOffering::query()->whereKey($offeringId)->exists()) {
            throw ValidationException::withMessages([
                'offering_id' => [__('gh.errors.offering_missing')],
            ]);
        }

        $price = $this->normalizePrice($data['contract_price'] ?? null);
        $currency = strtoupper((string) ($data['currency'] ?? 'AED'));
        if ($currency !== 'AED') {
            throw ValidationException::withMessages([
                'currency' => [__('gh.errors.currency_aed_only')],
            ]);
        }

        try {
            return GhB2bContractOffering::query()->create([
                'contract_id' => $contract->id,
                'offering_id' => $offeringId,
                'contract_price' => $price,
                'currency' => $currency,
                'status' => $data['status'] ?? GhCatalogStatus::ACTIVE,
            ]);
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'uq_gh_contract_offering') || str_contains($e->getMessage(), 'UNIQUE')) {
                throw ValidationException::withMessages([
                    'offering_id' => [__('gh.errors.contract_offering_duplicate')],
                ]);
            }
            throw $e;
        }
    }

    /**
     * @param  array{contract_price: mixed, currency?: string, status?: string}  $data
     */
    public function updateOffering(GhB2bContractOffering $row, array $data): GhB2bContractOffering
    {
        $price = $this->normalizePrice($data['contract_price'] ?? $row->contract_price);
        $currency = strtoupper((string) ($data['currency'] ?? $row->currency));
        if ($currency !== 'AED') {
            throw ValidationException::withMessages([
                'currency' => [__('gh.errors.currency_aed_only')],
            ]);
        }

        $row->update([
            'contract_price' => $price,
            'currency' => $currency,
            'status' => $data['status'] ?? $row->status,
        ]);

        // Never touch gh_product_offerings.standard_price
        return $row->refresh();
    }

    public function removeOffering(GhB2bContractOffering $row): void
    {
        $row->delete();
    }

    /**
     * Sync offerings in a transaction: optional full replace payload.
     *
     * @param  list<array{offering_id: int, contract_price: mixed, status?: string}>  $rows
     */
    public function syncOfferings(GhB2bContract $contract, array $rows): void
    {
        DB::transaction(function () use ($contract, $rows) {
            $keep = [];
            foreach ($rows as $row) {
                $offeringId = (int) $row['offering_id'];
                $existing = GhB2bContractOffering::query()
                    ->where('contract_id', $contract->id)
                    ->where('offering_id', $offeringId)
                    ->first();
                if ($existing) {
                    $this->updateOffering($existing, $row);
                    $keep[] = $existing->id;
                } else {
                    $created = $this->addOffering($contract, $row);
                    $keep[] = $created->id;
                }
            }
            GhB2bContractOffering::query()
                ->where('contract_id', $contract->id)
                ->whereNotIn('id', $keep)
                ->delete();
        });
    }

    private function assertB2bCustomer(int $customerId): GhCustomer
    {
        $customer = GhCustomer::query()->find($customerId);
        if ($customer === null) {
            throw ValidationException::withMessages([
                'customer_id' => [__('gh.errors.customer_missing')],
            ]);
        }
        if ($customer->type !== GhCustomerType::B2B) {
            throw ValidationException::withMessages([
                'customer_id' => [__('gh.errors.contract_requires_b2b')],
            ]);
        }

        return $customer;
    }

    /** @return array{0: Carbon, 1: ?Carbon} */
    private function normalizeDates(string $fromRaw, mixed $toRaw): array
    {
        try {
            $from = Carbon::parse($fromRaw)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'effective_from' => [__('gh.errors.contract_dates_invalid')],
            ]);
        }

        $to = null;
        if ($toRaw !== null && $toRaw !== '') {
            try {
                $to = Carbon::parse((string) $toRaw)->startOfDay();
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'effective_to' => [__('gh.errors.contract_dates_invalid')],
                ]);
            }
            if ($to->lt($from)) {
                throw ValidationException::withMessages([
                    'effective_to' => [__('gh.errors.contract_end_before_start')],
                ]);
            }
        }

        return [$from, $to];
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, GhContractStatus::ALL, true)) {
            throw ValidationException::withMessages([
                'status' => [__('gh.errors.contract_status_invalid')],
            ]);
        }
    }

    private function assertNoOverlap(int $customerId, Carbon $from, ?Carbon $to, string $status, ?int $ignoreId = null): void
    {
        if ($status !== GhContractStatus::ACTIVE) {
            return;
        }

        $q = GhB2bContract::query()
            ->where('customer_id', $customerId)
            ->where('status', GhContractStatus::ACTIVE);
        if ($ignoreId !== null) {
            $q->where('id', '!=', $ignoreId);
        }

        $newEnd = $to ?? Carbon::parse('9999-12-31');
        $conflict = $q->get()->first(function (GhB2bContract $existing) use ($from, $newEnd) {
            $exEnd = $existing->effective_to?->copy()->startOfDay() ?? Carbon::parse('9999-12-31');
            $exStart = $existing->effective_from->copy()->startOfDay();

            return $from->lte($exEnd) && $exStart->lte($newEnd);
        });

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'effective_from' => [__('gh.errors.contract_overlap', ['ref' => $conflict->reference])],
            ]);
        }
    }

    private function normalizePrice(mixed $raw): string
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw) || (float) $raw < 0) {
            throw ValidationException::withMessages([
                'contract_price' => [__('gh.errors.price_invalid')],
            ]);
        }

        return number_format(round((float) $raw, 2), 2, '.', '');
    }
}
