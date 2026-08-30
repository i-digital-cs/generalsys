<?php

namespace App\Http\Controllers\Gross;

use App\Http\Controllers\Controller;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemImageController extends Controller
{
    public function show(GrossItem $item, GrossItemImage $image): StreamedResponse
    {
        $this->authorize('view', $item);
        abort_unless((int) $image->item_id === (int) $item->id, 404);
        abort_unless(Storage::disk('local')->exists($image->file_path), 404);

        return Storage::disk('local')->response($image->file_path, $image->original_name);
    }
}
