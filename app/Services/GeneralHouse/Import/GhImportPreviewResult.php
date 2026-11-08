<?php

namespace App\Services\GeneralHouse\Import;

final class GhImportPreviewResult
{
    /**
     * @param  list<array{row: int, message: string}>  $errors
     * @param  list<array{row: int, key: string}>  $duplicates
     * @param  list<array<string, mixed>>  $rows  Serializable payloads that commit() will apply.
     */
    public function __construct(
        public readonly string $entity,
        public readonly int $total,
        public readonly int $valid,
        public readonly int $invalid,
        public readonly int $duplicate,
        public readonly int $create,
        public readonly int $update,
        public readonly array $errors,
        public readonly array $duplicates,
        public readonly array $rows,
    ) {}

    /**
     * @param  list<array{row: int, message: string}>  $errors
     * @param  list<array{row: int, key: string}>  $duplicates
     * @param  list<array<string, mixed>>  $rows
     */
    public static function fromOutcome(string $entity, int $total, int $valid, int $invalid, array $errors, array $duplicates, array $rows): self
    {
        return new self($entity, $total, $valid, $invalid, count($duplicates), count($rows), 0, $errors, $duplicates, $rows);
    }

    public function hasRowsToApply(): bool
    {
        return $this->rows !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['entity'],
            (int) $data['total'],
            (int) $data['valid'],
            (int) $data['invalid'],
            (int) $data['duplicate'],
            (int) $data['create'],
            (int) $data['update'],
            $data['errors'],
            $data['duplicates'],
            $data['rows'],
        );
    }
}
