<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only self profile (T-1.8). Salary rates are deliberately NOT
 * loaded or rendered — employees never see money fields (§1.8).
 */
class ProfileController extends Controller
{
    public function showSelf(Request $request): View
    {
        return $this->show($request->user());
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load([
            'profile', 'activeContract', 'qualifications', 'emergencyContacts', 'documents',
            'restDays' => fn ($query) => $query->orderBy('date'),
        ]);

        return view('employee.profile', ['employee' => $user]);
    }
}
