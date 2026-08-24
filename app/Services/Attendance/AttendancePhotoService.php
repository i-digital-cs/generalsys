<?php

namespace App\Services\Attendance;

use App\Exceptions\AttendanceException;
use App\Models\Attendance;
use App\Models\AttendancePhoto;
use App\Models\AttendanceSegment;
use App\Models\User;
use App\Services\Notifications\AppNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Optional punch photos (T-6.6 / T-6.7) + driver mid-day FIELD uploads (T-7.10).
 * Files on `local` disk outside web root.
 */
class AttendancePhotoService
{
    public function __construct(
        private readonly GeofenceService $geofence,
        private readonly AppNotificationService $notifications,
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, string|null>  $descriptions  Parallel captions (same index as $files)
     * @param  array{latitude?: float|int|string, longitude?: float|int|string, accuracy?: float|int|string|null}|null  $location
     * @return array<int, AttendancePhoto>
     */
    public function storeMany(
        Attendance $attendance,
        string $kind,
        array $files,
        array $descriptions = [],
        ?AttendanceSegment $segment = null,
        ?array $location = null,
    ): array {
        $files = array_values(array_filter($files, fn ($file) => $file instanceof UploadedFile));

        if ($files === []) {
            return [];
        }

        $this->assertKnownKind($kind);
        $descriptions = array_values($descriptions);
        $gps = $this->normalizeLocation($location);

        return DB::transaction(function () use ($attendance, $kind, $files, $descriptions, $segment, $gps) {
            $segment ??= $attendance->segments()
                ->when(
                    in_array($kind, [AttendancePhoto::KIND_CLOCK_IN, AttendancePhoto::KIND_FIELD], true),
                    fn ($q) => $q->whereNull('clock_out'),
                )
                ->orderByDesc('sequence')
                ->first();

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
                    $segment,
                    $gps,
                );
            }

            return $stored;
        });
    }

    /**
     * Driver mid-day field proof while a shift segment is open.
     *
     * @param  array<int, UploadedFile>  $files
     * @param  array<int, string|null>  $descriptions
     * @param  array{latitude: float|int|string, longitude: float|int|string, accuracy?: float|int|string|null}  $location
     * @return array<int, AttendancePhoto>
     */
    public function storeFieldUploads(User $user, array $files, array $descriptions, array $location): array
    {
        if (! $user->requiresPunchPhotos()) {
            throw AttendanceException::fieldUploadNotAllowed();
        }

        $open = $this->attendance->openAttendance($user);
        if ($open === null) {
            throw AttendanceException::noOpenAttendance();
        }

        if ($files === []
            || ! isset($location['latitude'], $location['longitude'])
            || $location['latitude'] === ''
            || $location['longitude'] === ''
            || $location['latitude'] === null
            || $location['longitude'] === null
        ) {
            throw AttendanceException::fieldUploadNotAllowed();
        }

        $segment = $this->attendance->openSegment($user);

        return $this->storeMany(
            $open,
            AttendancePhoto::KIND_FIELD,
            $files,
            $descriptions,
            $segment,
            $location,
        );
    }

    /**
     * @param  array{latitude?: float|int|string, longitude?: float|int|string, accuracy?: float|int|string|null}|null  $location
     * @return array{lat: float, lng: float, accuracy_m: ?int, distance_m: ?int, outside: bool}|null
     */
    private function normalizeLocation(?array $location): ?array
    {
        if ($location === null
            || ! isset($location['latitude'], $location['longitude'])
            || $location['latitude'] === ''
            || $location['longitude'] === ''
            || $location['latitude'] === null
            || $location['longitude'] === null
        ) {
            return null;
        }

        $lat = (float) $location['latitude'];
        $lng = (float) $location['longitude'];
        $accuracy = array_key_exists('accuracy', $location) && $location['accuracy'] !== null && $location['accuracy'] !== ''
            ? (int) round((float) $location['accuracy'])
            : null;

        $eval = $this->geofence->evaluate($lat, $lng);

        return [
            'lat' => $lat,
            'lng' => $lng,
            'accuracy_m' => $accuracy,
            'distance_m' => $eval['distance_m'],
            'outside' => (bool) $eval['outside'],
        ];
    }

    /**
     * @param  array{lat: float, lng: float, accuracy_m: ?int, distance_m: ?int, outside: bool}|null  $gps
     */
    private function storeOne(
        Attendance $attendance,
        string $kind,
        UploadedFile $file,
        int $sortOrder,
        ?string $description,
        ?AttendanceSegment $segment = null,
        ?array $gps = null,
    ): AttendancePhoto {
        $path = $file->storeAs(
            "attendance-photos/{$attendance->user_id}/{$attendance->id}",
            Str::random(40).'.'.$file->getClientOriginalExtension(),
            'local',
        );

        $caption = $description !== null ? trim($description) : '';

        $photo = $attendance->photos()->create([
            'attendance_segment_id' => $segment?->id,
            'kind' => $kind,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'description' => $caption !== '' ? mb_substr($caption, 0, 500) : null,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'sort_order' => $sortOrder,
            'uploaded_at' => now(),
            'lat' => $gps['lat'] ?? null,
            'lng' => $gps['lng'] ?? null,
            'accuracy_m' => $gps['accuracy_m'] ?? null,
            'distance_m' => $gps['distance_m'] ?? null,
            'outside_geofence' => (bool) ($gps['outside'] ?? false),
        ]);

        if ($gps !== null && $gps['outside'] && $gps['distance_m'] !== null) {
            $office = $this->geofence->office();
            $this->notifications->attendanceGeofenceWarning(
                $attendance,
                'FIELD',
                $gps['distance_m'],
                (int) ($office['radius_m'] ?? config('attendance.location.radius_meters', 1000)),
                $gps['lat'],
                $gps['lng'],
            );
        }

        return $photo;
    }

    private function assertKnownKind(string $kind): void
    {
        if (! in_array($kind, [
            AttendancePhoto::KIND_CLOCK_IN,
            AttendancePhoto::KIND_CLOCK_OUT,
            AttendancePhoto::KIND_FIELD,
        ], true)) {
            throw new \InvalidArgumentException('Invalid attendance photo kind.');
        }
    }
}
