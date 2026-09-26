<?php

namespace App\Http\Controllers\Gross;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\ImportGrossItemAttributeRequest;
use App\Http\Requests\Gross\StoreGrossItemAttributeRequest;
use App\Http\Requests\Gross\UpdateGrossItemAttributeRequest;
use App\Models\Gross\GrossItemAttribute;
use App\Services\Gross\ItemAttributeService;
use App\Support\GrossAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemAttributeController extends Controller
{
    public function __construct(private readonly ItemAttributeService $attributes) {}

    public function index(Request $request): View
    {
        abort_unless(GrossAccess::mayPage($request->user(), 'attributes'), 403);

        $tab = strtoupper((string) $request->query('tab', GrossItemAttribute::KIND_COLOR));
        if (! in_array($tab, GrossItemAttribute::KINDS, true)) {
            $tab = GrossItemAttribute::KIND_COLOR;
        }

        $q = trim((string) $request->query('q', ''));
        $grouped = $this->attributes->grouped($q);

        if ($q !== '' && ($grouped[$tab] ?? collect())->isEmpty()) {
            foreach (GrossItemAttribute::KINDS as $kind) {
                if (($grouped[$kind] ?? collect())->isNotEmpty()) {
                    $tab = $kind;
                    break;
                }
            }
        }

        return view('gross.attributes.index', [
            'grouped' => $grouped,
            'activeTab' => $tab,
            'filters' => ['q' => $q],
        ]);
    }

    public function store(StoreGrossItemAttributeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->attributes->create($data);

        return redirect()
            ->route('gross.attributes.index', ['tab' => $data['kind']])
            ->with('success', __('gross.flash.attribute_created'));
    }

    public function update(UpdateGrossItemAttributeRequest $request, GrossItemAttribute $attribute): RedirectResponse
    {
        $this->attributes->update($attribute, $request->validated());

        return redirect()
            ->route('gross.attributes.index', ['tab' => $attribute->kind])
            ->with('success', __('gross.flash.attribute_updated'));
    }

    public function destroy(GrossItemAttribute $attribute): RedirectResponse
    {
        abort_unless(GrossAccess::mayPage(request()->user(), 'attributes'), 403);
        $kind = $attribute->kind;
        $this->attributes->delete($attribute);

        return redirect()
            ->route('gross.attributes.index', ['tab' => $kind])
            ->with('success', __('gross.flash.attribute_deleted'));
    }

    public function template(): BinaryFileResponse|StreamedResponse
    {
        abort_unless(GrossAccess::mayPage(request()->user(), 'attributes'), 403);

        $path = storage_path('app/tmp/gross-attributes-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $this->attributes->writeTemplate($path);

        return response()->download(
            $path,
            'gross-attributes-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function import(ImportGrossItemAttributeRequest $request): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $result = $this->attributes->importFromPath((string) $path);

        $message = __('gross.flash.attribute_imported', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);

        $redirect = redirect()->route('gross.attributes.index');

        if ($result['errors'] !== []) {
            return $redirect
                ->with('warning', $message)
                ->withErrors(['import' => $result['errors']]);
        }

        return $redirect->with('success', $message);
    }
}
