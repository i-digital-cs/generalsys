<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the employee personal photo from the `local` disk —
 * MANAGER or the employee themselves (EmployeePolicy::view).
 */
class EmployeePhotoController extends Controller
{
    public function show(User $user): StreamedResponse
    {
        $this->authorize('view', $user);

        $path = $user->profile?->personal_photo_path;

        abort_unless($path !== null && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
