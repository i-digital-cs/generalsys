<?php

namespace App\Http\Controllers;

use App\Models\AttendancePhoto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a punch photo from the `local` disk (AttendancePhotoPolicy::view).
 */
class AttendancePhotoController extends Controller
{
    public function show(AttendancePhoto $photo): StreamedResponse
    {
        $photo->loadMissing('attendance.user');

        $this->authorize('view', $photo);

        abort_unless(Storage::disk('local')->exists($photo->file_path), 404);

        return Storage::disk('local')->response($photo->file_path);
    }
}
