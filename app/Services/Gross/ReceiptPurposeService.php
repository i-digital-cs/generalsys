<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossReceiptPurpose;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReceiptPurposeService
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossReceiptPurpose::query()
            ->withCount('vouchers')
            ->orderBy('sort_order')
            ->orderBy('name_en');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('name_ar', 'like', $like)
                    ->orWhere('name_en', 'like', $like);
            });
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'active') {
            $q->where('is_active', true);
        } elseif ($status === 'inactive') {
            $q->where('is_active', false);
        }

        return $q->paginate(min($perPage, 50))->withQueryString();
    }

    /**
     * @return array{total: int, active: int, in_use: int}
     */
    public function summary(): array
    {
        return [
            'total' => GrossReceiptPurpose::query()->count(),
            'active' => GrossReceiptPurpose::query()->where('is_active', true)->count(),
            'in_use' => GrossReceiptPurpose::query()->whereHas('vouchers')->count(),
        ];
    }

    public function activeForForms(): Collection
    {
        return GrossReceiptPurpose::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * @param  array{name_ar: string, name_en: string, is_active?: bool}  $data
     */
    public function create(array $data): GrossReceiptPurpose
    {
        return GrossReceiptPurpose::query()->create([
            'name_ar' => trim($data['name_ar']),
            'name_en' => trim($data['name_en']),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => 0,
        ]);
    }

    /**
     * @param  array{name_ar: string, name_en: string, is_active?: bool}  $data
     */
    public function update(GrossReceiptPurpose $purpose, array $data): GrossReceiptPurpose
    {
        $purpose->fill([
            'name_ar' => trim($data['name_ar']),
            'name_en' => trim($data['name_en']),
            'is_active' => (bool) ($data['is_active'] ?? $purpose->is_active),
        ]);
        $purpose->save();

        return $purpose->fresh();
    }

    public function delete(GrossReceiptPurpose $purpose): void
    {
        if ($purpose->vouchers()->exists()) {
            throw GrossException::receiptPurposeInUse();
        }

        DB::transaction(function () use ($purpose) {
            $purpose->delete();
        });
    }
}
