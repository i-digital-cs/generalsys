<?php

namespace App\Services\GeneralHouse\Import;

use App\Models\User;
use App\Services\GeneralHouse\Import\Importers\CategoryImporter;
use App\Services\GeneralHouse\Import\Importers\ContractImporter;
use App\Services\GeneralHouse\Import\Importers\CustomerImporter;
use App\Services\GeneralHouse\Import\Importers\GhEntityImporter;
use App\Services\GeneralHouse\Import\Importers\OfferingImporter;
use App\Services\GeneralHouse\Import\Importers\ProductImporter;
use App\Services\GeneralHouse\Import\Importers\TreatmentImporter;
use App\Services\GeneralHouse\Import\Importers\ZoneImporter;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Orchestrates upload → preview (cached 30 min) → confirm for every import-enabled entity.
 */
class GhImportService
{
    /** @var array<string, class-string<GhEntityImporter>> */
    public const IMPORTERS = [
        'categories' => CategoryImporter::class,
        'treatments' => TreatmentImporter::class,
        'products' => ProductImporter::class,
        'offerings' => OfferingImporter::class,
        'zones' => ZoneImporter::class,
        'customers' => CustomerImporter::class,
        'contracts' => ContractImporter::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly GhSpreadsheetSupport $sheets,
        private readonly GhImportSession $session,
        private readonly GhZipImageArchive $zips,
    ) {}

    public function importer(string $entity): ?GhEntityImporter
    {
        $class = self::IMPORTERS[$entity] ?? null;

        return $class === null ? null : $this->container->make($class);
    }

    /** @return string Preview token. */
    public function startPreview(GhEntityImporter $importer, UploadedFile $file, ?UploadedFile $zip, User $user): string
    {
        $rows = $this->sheets->readRows(
            (string) $file->getRealPath(),
            $importer->requiredHeaders(),
            $importer->headers(),
            $importer->dateHeaders(),
        );

        $archiveFiles = [];
        $archiveDir = null;
        if ($zip !== null && $importer->entityKey() === 'products') {
            $this->zips->purgeStale();
            $archiveDir = $this->zips->baseDirectory().'/'.Str::random(24);
            $archiveFiles = $this->zips->extract((string) $zip->getRealPath(), $archiveDir);
        }

        $preview = $importer->preview($rows, ['archive_files' => $archiveFiles]);

        return $this->session->put($user->id, $importer->entityKey(), $preview, $archiveFiles, $archiveDir);
    }

    public function previewFor(string $entity, string $token, User $user): ?GhImportPreviewResult
    {
        return $this->session->get($token, $user->id, $entity)['preview'] ?? null;
    }

    public function confirm(GhEntityImporter $importer, string $token, User $user): ?GhImportCommitResult
    {
        $stored = $this->session->get($token, $user->id, $importer->entityKey());
        if ($stored === null) {
            return null;
        }

        $this->session->forget($token);
        try {
            return $importer->commit($stored['preview'], ['archive_files' => $stored['archive_files']]);
        } finally {
            $this->zips->deleteDirectory($stored['archive_dir']);
        }
    }

    public function sheets(): GhSpreadsheetSupport
    {
        return $this->sheets;
    }
}
