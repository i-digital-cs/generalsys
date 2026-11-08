<?php

namespace App\Services\GeneralHouse\Catalog;

use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Support\GeneralHouse\GhCatalogStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GhProductService
{
    public const IMAGE_MAX_KB = 5120;

    /** @var list<string> */
    public const IMAGE_MIMES = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * @param  array{q?: string, status?: string, category_id?: int|string}  $filters
     * @return LengthAwarePaginator<int, GhProduct>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $perPage = max(1, min(50, $perPage));
        $q = GhProduct::query()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name_en');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('name_ar', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        }

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, GhCatalogStatus::ALL, true)) {
            $q->where('status', $status);
        }

        $categoryId = $filters['category_id'] ?? null;
        if ($categoryId !== null && $categoryId !== '') {
            $q->where('category_id', (int) $categoryId);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{category_id: int, code: string, name_ar: string, name_en: string, status?: string, sort_order?: int}  $data
     */
    public function create(array $data, ?UploadedFile $image = null): GhProduct
    {
        $this->assertCategoryExists((int) $data['category_id']);
        $this->assertUniqueCode((string) $data['code']);

        return DB::transaction(function () use ($data, $image) {
            $product = GhProduct::query()->create([
                'category_id' => (int) $data['category_id'],
                'code' => strtoupper(trim((string) $data['code'])),
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'image_path' => null,
                'status' => $data['status'] ?? GhCatalogStatus::ACTIVE,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            if ($image !== null) {
                $product->update(['image_path' => $this->storeImage($product, $image)]);
            }

            return $product->refresh();
        });
    }

    /**
     * @param  array{category_id: int, code: string, name_ar: string, name_en: string, status?: string, sort_order?: int, remove_image?: bool}  $data
     */
    public function update(GhProduct $product, array $data, ?UploadedFile $image = null): GhProduct
    {
        $this->assertCategoryExists((int) $data['category_id']);
        $this->assertUniqueCode((string) $data['code'], $product->id);

        return DB::transaction(function () use ($product, $data, $image) {
            $path = $product->image_path;
            if (! empty($data['remove_image'])) {
                $this->deleteStoredImage($path);
                $path = null;
            }
            if ($image !== null) {
                $this->deleteStoredImage($path);
                $path = $this->storeImage($product, $image);
            }

            $product->update([
                'category_id' => (int) $data['category_id'],
                'code' => strtoupper(trim((string) $data['code'])),
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'image_path' => $path,
                'status' => $data['status'] ?? $product->status,
                'sort_order' => (int) ($data['sort_order'] ?? $product->sort_order),
            ]);

            return $product->refresh();
        });
    }

    public function setStatus(GhProduct $product, string $status): GhProduct
    {
        if (! in_array($status, GhCatalogStatus::ALL, true)) {
            throw ValidationException::withMessages([
                'status' => [__('gh.errors.invalid_status')],
            ]);
        }

        $product->update(['status' => $status]);

        return $product->refresh();
    }

    public function delete(GhProduct $product): void
    {
        if ($product->offerings()->exists()) {
            throw ValidationException::withMessages([
                'product' => [__('gh.errors.product_has_offerings')],
            ]);
        }

        DB::transaction(function () use ($product) {
            $this->deleteStoredImage($product->image_path);
            $product->delete();
        });
    }

    private function assertCategoryExists(int $categoryId): void
    {
        if (! GhServiceCategory::query()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages([
                'category_id' => [__('gh.errors.category_missing')],
            ]);
        }
    }

    private function assertUniqueCode(string $code, ?int $ignoreId = null): void
    {
        $code = strtoupper(trim($code));
        $q = GhProduct::query()->where('code', $code);
        if ($ignoreId !== null) {
            $q->where('id', '!=', $ignoreId);
        }
        if ($q->exists()) {
            throw ValidationException::withMessages([
                'code' => [__('gh.errors.product_code_taken')],
            ]);
        }
    }

    /**
     * Attach an already validated local image (e.g. import) as the product's single image.
     */
    public function attachStoredImage(GhProduct $product, string $absolutePath, string $ext): string
    {
        $ext = strtolower($ext);
        if (! in_array($ext, self::IMAGE_MIMES, true)) {
            $ext = 'jpg';
        }

        $path = Storage::disk('local')->putFileAs(
            'gh-product-images/'.$product->id,
            new File($absolutePath),
            Str::random(40).'.'.$ext
        );
        $this->deleteStoredImage($product->image_path);
        $product->update(['image_path' => $path]);

        return $path;
    }

    private function storeImage(GhProduct $product, UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, self::IMAGE_MIMES, true)) {
            $ext = 'jpg';
        }

        return $file->storeAs(
            'gh-product-images/'.$product->id,
            Str::random(40).'.'.$ext,
            'local'
        );
    }

    private function deleteStoredImage(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
