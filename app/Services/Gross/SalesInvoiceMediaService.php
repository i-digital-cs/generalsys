<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Optional gallery images on sales invoices (show page only). Packing photo stays separate.
 */
class SalesInvoiceMediaService
{
    public const MAX_PER_INVOICE = 10;

    public function store(
        GrossSalesInvoice $invoice,
        UploadedFile $file,
        User $actor,
        ?string $title = null,
        ?string $description = null,
    ): GrossSalesInvoiceMedia {
        return DB::transaction(function () use ($invoice, $file, $actor, $title, $description) {
            $existing = $invoice->media()->count();
            if ($existing >= self::MAX_PER_INVOICE) {
                throw GrossException::tooManySalesInvoiceMedia();
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $ext = 'jpg';
            }

            $path = $file->storeAs(
                'gross-sales-media/'.$invoice->id,
                Str::random(40).'.'.$ext,
                'local'
            );

            return $invoice->media()->create([
                'title' => $this->nullableTrim($title, 120),
                'description' => $this->nullableTrim($description, 1000),
                'original_name' => Str::limit((string) $file->getClientOriginalName(), 240, ''),
                'file_path' => $path,
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'uploaded_by' => $actor->id,
            ]);
        });
    }

    public function delete(GrossSalesInvoiceMedia $media): void
    {
        DB::transaction(function () use ($media) {
            if ($media->file_path && Storage::disk('local')->exists($media->file_path)) {
                Storage::disk('local')->delete($media->file_path);
            }
            $media->delete();
        });
    }

    public function response(GrossSalesInvoiceMedia $media): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($media->file_path), 404);

        return Storage::disk('local')->response(
            $media->file_path,
            $media->original_name ?: 'media.jpg',
            ['Content-Type' => $media->mime_type ?: 'image/jpeg']
        );
    }

    private function nullableTrim(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return Str::limit($trimmed, $max, '');
    }
}
