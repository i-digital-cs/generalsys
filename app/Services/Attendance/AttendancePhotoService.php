<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Optional punch photos (T-6.6) — store on `local` disk outside web root.
 */
class AttendancePhotoService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, AttendancePhoto>
     */
    public function storeMany(Attendance $attendance, string $kind, array $files): array
    {
        $files = array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));

        if ($files === []) {
            return [];
        }

        $this->assertKnownKind($kind);

        return DB::transaction(function () use ($attendance, $kind, $files) {
            $startOrder = (int) $attendance->photos()
                ->where('kind', $kind)
                ->max('sort_order');

            $stored = [];
            foreach ($files as $index => $file) {
                $stored[] = $this->storeOne($attendance, $kind, $file, $startOrder + $index + 1);
            }

            return $stored;
        });
    }

    private function storeOne(Attendance $attendance, string $kind, UploadedFile $file, int $sortOrder): AttendancePhoto
    {
        $path = $file->storeAs(
            "attendance-photos/{$attendance->user_id}/{$attendance->id}",
            Str::random(40).'.'.$file->getClientOriginalExtension(),
            'local',
        );

        return $attendance->photos()->create([
            'kind' => $kind,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
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
