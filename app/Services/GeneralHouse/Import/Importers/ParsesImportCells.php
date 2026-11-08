<?php

namespace App\Services\GeneralHouse\Import\Importers;

use App\Services\GeneralHouse\Import\GhImportRowException;
use App\Support\GeneralHouse\GhCatalogStatus;

trait ParsesImportCells
{
    /** @param  array<string, string>  $data */
    protected function text(array $data, string $key, int $max, bool $required = false): ?string
    {
        $value = trim($data[$key] ?? '');
        if ($value === '') {
            if ($required) {
                throw new GhImportRowException(__('gh.import.errors.required', ['field' => $key]));
            }

            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new GhImportRowException(__('gh.import.errors.too_long', ['field' => $key, 'max' => $max]));
        }

        return $value;
    }

    /** @param  array<string, string>  $data */
    protected function status(array $data, string $key = 'status'): string
    {
        return $this->enum($data, $key, GhCatalogStatus::ALL, GhCatalogStatus::ACTIVE);
    }

    /**
     * @param  array<string, string>  $data
     * @param  list<string>  $allowed
     */
    protected function enum(array $data, string $key, array $allowed, string $default): string
    {
        $value = strtoupper(trim($data[$key] ?? ''));
        if ($value === '') {
            return $default;
        }
        if (! in_array($value, $allowed, true)) {
            throw new GhImportRowException(__('gh.import.errors.invalid_choice', ['field' => $key, 'allowed' => implode(', ', $allowed)]));
        }

        return $value;
    }

    /** @param  array<string, string>  $data */
    protected function sortOrder(array $data): int
    {
        $value = trim($data['sort_order'] ?? '');
        if ($value === '') {
            return 0;
        }
        if (preg_match('/^\d{1,6}$/', $value) !== 1) {
            throw new GhImportRowException(__('gh.import.errors.invalid_number', ['field' => 'sort_order']));
        }

        return (int) $value;
    }

    /** @param  array<string, string>  $data */
    protected function money(array $data, string $key, bool $required): ?string
    {
        $value = trim($data[$key] ?? '');
        if ($value === '') {
            if ($required) {
                throw new GhImportRowException(__('gh.import.errors.required', ['field' => $key]));
            }

            return null;
        }
        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $value) !== 1) {
            throw new GhImportRowException(__('gh.import.errors.invalid_amount', ['field' => $key]));
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function lower(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
