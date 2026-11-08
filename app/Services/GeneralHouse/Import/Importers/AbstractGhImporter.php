<?php

namespace App\Services\GeneralHouse\Import\Importers;

use App\Services\GeneralHouse\Import\GhImportCommitResult;
use App\Services\GeneralHouse\Import\GhImportPreviewResult;
use App\Services\GeneralHouse\Import\GhImportRowException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Template-method base: one row = one record; duplicates are skipped, never overwritten.
 */
abstract class AbstractGhImporter implements GhEntityImporter
{
    use ParsesImportCells;

    public function requiredHeaders(): array
    {
        return [];
    }

    public function dateHeaders(): array
    {
        return [];
    }

    /**
     * Validate one row and return the serializable payload to apply.
     *
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     *
     * @throws GhImportRowException
     */
    abstract protected function parseRow(array $data, array $context): array;

    /** @param  array<string, mixed>  $payload */
    abstract protected function duplicateKey(array $payload): string;

    /** @param  array<string, mixed>  $payload */
    abstract protected function existsInDatabase(array $payload): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @param  list<string>  $imageErrors
     */
    abstract protected function persist(array $payload, array $context, int $row, array &$imageErrors): void;

    public function preview(array $rows, array $context = []): GhImportPreviewResult
    {
        $errors = [];
        $duplicates = [];
        $accepted = [];
        $seen = [];

        foreach ($rows as $row) {
            try {
                $payload = $this->parseRow($row['data'], $context);
                $key = $this->duplicateKey($payload);
                if (isset($seen[$key]) || $this->existsInDatabase($payload)) {
                    $duplicates[] = ['row' => $row['line'], 'key' => $key];

                    continue;
                }
                $seen[$key] = true;
                $accepted[] = ['row' => $row['line'], 'payload' => $payload];
            } catch (GhImportRowException $e) {
                $errors[] = ['row' => $row['line'], 'message' => $e->getMessage()];
            }
        }

        return GhImportPreviewResult::fromOutcome(
            $this->entityKey(), count($rows), count($accepted), count($errors), $errors, $duplicates, $accepted
        );
    }

    public function commit(GhImportPreviewResult $preview, array $context = []): GhImportCommitResult
    {
        $created = 0;
        $skipped = 0;
        $errors = [];
        $imageErrors = [];

        foreach ($preview->rows as $item) {
            if ($this->existsInDatabase($item['payload'])) {
                $skipped++;

                continue;
            }
            try {
                DB::transaction(function () use ($item, $context, &$imageErrors): void {
                    $this->persist($item['payload'], $context, $item['row'], $imageErrors);
                });
                $created++;
            } catch (ValidationException $e) {
                $errors[] = __('gh.import.errors.row_prefix', ['row' => $item['row']]).' '.collect($e->errors())->flatten()->first();
            }
        }

        return new GhImportCommitResult($created, $skipped + $preview->duplicate, 0, $errors, $imageErrors);
    }
}
