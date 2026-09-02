<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\Gross\GrossTreasuryVoucherAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Treasury voucher proof files — local disk, random filenames (AGENTS.md §5.5).
 */
class TreasuryVoucherAttachmentService
{
    public const MAX_PER_VOUCHER = 5;

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, GrossTreasuryVoucherAttachment>
     */
    public function storeMany(GrossTreasuryVoucher $voucher, array $files, User $actor): array
    {
        if ($files === []) {
            return [];
        }

        return DB::transaction(function () use ($voucher, $files, $actor) {
            $existing = $voucher->attachments()->count();
            if ($existing + count($files) > self::MAX_PER_VOUCHER) {
                throw GrossException::tooManyTreasuryAttachments();
            }

            $stored = [];
            foreach ($files as $file) {
                $stored[] = $this->store($voucher, $file, $actor);
            }

            return $stored;
        });
    }

    public function download(GrossTreasuryVoucherAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->original_name,
        );
    }

    private function store(GrossTreasuryVoucher $voucher, UploadedFile $file, User $actor): GrossTreasuryVoucherAttachment
    {
        $path = $file->storeAs(
            "gross-treasury-vouchers/{$voucher->id}",
            Str::random(40).'.'.$file->extension(),
            'local',
        );

        return $voucher->attachments()->create([
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);
    }
}
