<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\PreviewGhImportRequest;
use App\Services\GeneralHouse\Import\GhImportService;
use App\Services\GeneralHouse\Import\Importers\GhEntityImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportController extends Controller
{
    public function __construct(private readonly GhImportService $imports) {}

    public function template(Request $request, string $entity): BinaryFileResponse
    {
        $importer = $this->authorizedImporter($request, $entity);

        return $this->imports->sheets()->downloadTemplate($importer);
    }

    public function preview(PreviewGhImportRequest $request, string $entity): RedirectResponse
    {
        $importer = $this->authorizedImporter($request, $entity);
        $token = $this->imports->startPreview($importer, $request->file('file'), $request->file('zip'), $request->user());

        return redirect()->route('gh.import.preview', ['entity' => $entity, 'token' => $token]);
    }

    public function showPreview(Request $request, string $entity, string $token): View|RedirectResponse
    {
        $importer = $this->authorizedImporter($request, $entity);
        $preview = $this->imports->previewFor($entity, $token, $request->user());
        if ($preview === null) {
            return $this->expired($entity);
        }

        return view('gh.import.preview', [
            'entity' => $entity,
            'token' => $token,
            'preview' => $preview,
            'importer' => $importer,
        ]);
    }

    public function confirm(Request $request, string $entity, string $token): RedirectResponse
    {
        $importer = $this->authorizedImporter($request, $entity);
        $result = $this->imports->confirm($importer, $token, $request->user());
        if ($result === null) {
            return $this->expired($entity);
        }

        $redirect = redirect()->route('gh.'.$entity.'.index')->with(
            $result->hasProblems() ? 'warning' : 'success',
            __('gh.import.flash.done', ['created' => $result->created, 'skipped' => $result->skipped]),
        );

        return $result->hasProblems()
            ? $redirect->with('gh_import_problems', array_merge($result->errors, $result->imageErrors))
            : $redirect;
    }

    public function errors(Request $request, string $entity, string $token): BinaryFileResponse|RedirectResponse
    {
        $this->authorizedImporter($request, $entity);
        $preview = $this->imports->previewFor($entity, $token, $request->user());
        if ($preview === null) {
            return $this->expired($entity);
        }

        return $this->imports->sheets()->downloadErrors($entity, $preview->errors);
    }

    private function authorizedImporter(Request $request, string $entity): GhEntityImporter
    {
        $importer = $this->imports->importer($entity);
        abort_if($importer === null, 404);
        $this->authorize('create', $importer->modelClass());

        return $importer;
    }

    private function expired(string $entity): RedirectResponse
    {
        return redirect()->route('gh.'.$entity.'.index')->with('error', __('gh.import.flash.expired'));
    }
}
