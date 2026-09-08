<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossExpenseType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseTypeService
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossExpenseType::query()
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
            'total' => GrossExpenseType::query()->count(),
            'active' => GrossExpenseType::query()->where('is_active', true)->count(),
            'in_use' => GrossExpenseType::query()->whereHas('vouchers')->count(),
        ];
    }

    public function activeForForms(): Collection
    {
        return GrossExpenseType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * @param  array{name_ar: string, name_en: string, is_active?: bool}  $data
     */
    public function create(array $data): GrossExpenseType
    {
        return GrossExpenseType::query()->create([
            'name_ar' => trim($data['name_ar']),
            'name_en' => trim($data['name_en']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array{name_ar: string, name_en: string, is_active?: bool}  $data
     */
    public function update(GrossExpenseType $type, array $data): GrossExpenseType
    {
        $type->fill([
            'name_ar' => trim($data['name_ar']),
            'name_en' => trim($data['name_en']),
            'is_active' => (bool) ($data['is_active'] ?? $type->is_active),
        ]);
        $type->save();

        return $type->fresh();
    }

    public function delete(GrossExpenseType $type): void
    {
        if ($type->vouchers()->exists()) {
            throw GrossException::expenseTypeInUse();
        }

        DB::transaction(function () use ($type) {
            $type->delete();
        });
    }
}
