<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Optional punch photos (T-6.6 / T-6.7) — store on `local` disk outside web root.
 */
class AttendancePhotoService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, string|null>  $descriptions  Parallel captions (same index as $files)
     * @return array<int, AttendancePhoto>
     */
    public function storeMany(Attendance $attendance, string $kind, array $files, array $descriptions = []): array
    {
        $files = array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));

        if ($files === []) {
            return [];
        }

        $this->assertKnownKind($kind);
        $descriptions = array_values($descriptions);

        return DB::transaction(function () use ($attendance, $kind, $files, $descriptions) {
            $startOrder = (int) $attendance->photos()
                ->where('kind', $kind)
                ->max('sort_order');

            $stored = [];
            foreach ($files as $index => $file) {
                $caption = $descriptions[$index] ?? null;
                $stored[] = $this->storeOne(
                    $attendance,
                    $kind,
                    $file,
                    $startOrder + $index + 1,
                    is_string($caption) ? $caption : null,
                );
            }

            return $stored;
        });
    }

    private function storeOne(
        Attendance $attendance,
        string $kind,
        UploadedFile $file,
        int $sortOrder,
        ?string $description,
    ): AttendancePhoto {
        $path = $file->storeAs(
            "attendance-photos/{$attendance->user_id}/{$attendance->id}",
            Str::random(40).'.'.$file->getClientOriginalExtension(),
            'local',
        );

        $caption = $description !== null ? trim($description) : '';

        return $attendance->photos()->create([
            'kind' => $kind,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'description' => $caption !== '' ? mb_substr($caption, 0, 500) : null,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'sort_order' => $sortOrder,
            'uploaded_at' => now(),
        ]);
    }

    private function assertKnownKind(string $kind): void
    {
        if (! in_array($kind, [AttendancePhoto::KIND_CLOCK_IN, AttendancePhoto::KIND_CLOCK_OUT], true)) {
            throw new \InvalidArgumentException('Invalid attendance photo kind.');
        }
    }
}
