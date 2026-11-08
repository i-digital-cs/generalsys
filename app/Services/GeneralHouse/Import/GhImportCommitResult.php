<?php

namespace App\Services\GeneralHouse\Import;

final class GhImportCommitResult
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $imageErrors
     */
    public function __construct(
        public readonly int $created,
        public readonly int $skipped,
        public readonly int $updated,
        public readonly array $errors,
        public readonly array $imageErrors = [],
    ) {}

    public function hasProblems(): bool
    {
        return $this->errors !== [] || $this->imageErrors !== [];
    }
}
