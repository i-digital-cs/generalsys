<?php

namespace App\Services\GeneralHouse\Import\Importers;

use App\Services\GeneralHouse\Import\GhImportCommitResult;
use App\Services\GeneralHouse\Import\GhImportPreviewResult;

interface GhEntityImporter
{
    public function entityKey(): string;

    /** @return class-string Model whose `create` policy gates this import. */
    public function modelClass(): string;

    public function templateFilename(): string;

    /** @return list<string> */
    public function headers(): array;

    /** @return list<string> */
    public function requiredHeaders(): array;

    /** @return list<string> */
    public function dateHeaders(): array;

    /** @return list<list<string>> 5–8 realistic example rows */
    public function exampleRows(): array;

    /** @return list<string> */
    public function instructionLines(): array;

    /** @return list<list<string>> First row is the header row. */
    public function referenceRows(): array;

    /**
     * @param  list<array{line: int, data: array<string, string>}>  $rows
     * @param  array<string, mixed>  $context
     */
    public function preview(array $rows, array $context = []): GhImportPreviewResult;

    /** @param  array<string, mixed>  $context */
    public function commit(GhImportPreviewResult $preview, array $context = []): GhImportCommitResult;
}
