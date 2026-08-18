<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ImpersonationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StartImpersonationRequest;
use App\Models\User;
use App\Services\Auth\ImpersonationService;
use App\Services\Auth\PostLoginRedirector;
use Illuminate\Http\RedirectResponse;

class ImpersonationController extends Controller
{
    public function __construct(
        private readonly ImpersonationService $impersonation,
        private readonly PostLoginRedirector $redirector,
    ) {}

    public function store(StartImpersonationRequest $request, User $employee): RedirectResponse
    {
        try {
            $this->impersonation->start($request->user(), $employee);
        } catch (ImpersonationException $e) {
            return redirect()->back()->with('error', __($e->getMessage()));
        }

        return redirect()
            ->to($this->redirector->urlFor($employee))
            ->with('success', __('impersonation.flash.started', [
                'name' => $this->impersonation->displayName($employee),
            ]));
    }

    public function destroy(): RedirectResponse
    {
        try {
            $admin = $this->impersonation->stop();
        } catch (ImpersonationException $e) {
            $sendToLogin = $e->getMessage() === 'impersonation.errors.impersonator_missing';

            return redirect()
                ->to($sendToLogin ? route('login') : url()->previous(route('dashboard')))
                ->with('error', __($e->getMessage()));
        }

        return redirect()
            ->to($this->redirector->urlFor($admin))
            ->with('success', __('impersonation.flash.stopped'));
    }
}
