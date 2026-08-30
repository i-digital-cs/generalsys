<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ItemImageService
{
    public const MAX_PER_ITEM = 10;

    public const MAX_KB = 5120;

    /** @var list<string> */
    public const MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * @param  list<UploadedFile>  $files
     */
    public function attachMany(GrossItem $item, array $files): void
    {
        $files = array_values(array_filter($files));
        if ($files === []) {
            return;
        }

        $existing = $item->images()->count();
        if ($existing + count($files) > self::MAX_PER_ITEM) {
            throw ValidationException::withMessages([
                'images' => [__('gross.errors.images_max', ['max' => self::MAX_PER_ITEM])],
            ]);
        }

        $nextSort = (int) $item->images()->max('sort_order');

        foreach ($files as $file) {
            $nextSort++;
            $this->storeOne($item, $file, $nextSort);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteMany(GrossItem $item, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $images = $item->images()->whereIn('id', $ids)->get();
        foreach ($images as $image) {
            $this->deleteOne($image);
        }
    }

    public function deleteOne(GrossItemImage $image): void
    {
        if ($image->file_path !== '' && Storage::disk('local')->exists($image->file_path)) {
            Storage::disk('local')->delete($image->file_path);
        }
        $image->delete();
    }

    private function storeOne(GrossItem $item, UploadedFile $file, int $sortOrder): GrossItemImage
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, self::MIMES, true)) {
            $ext = 'jpg';
        }

        $path = $file->storeAs(
            'gross-item-images/'.$item->id,
            Str::random(40).'.'.$ext,
            'local'
        );

        return GrossItemImage::query()->create([
            'item_id' => $item->id,
            'file_path' => $path,
            'original_name' => Str::limit((string) $file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'sort_order' => $sortOrder,
        ]);
    }
}
