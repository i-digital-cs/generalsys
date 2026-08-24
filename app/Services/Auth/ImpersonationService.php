<?php

namespace App\Services\Auth;

use App\Exceptions\ImpersonationException;
use App\Models\User;
use App\Services\Admin\SettingService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * Admin "login as user" (session switch). Off unless Settings enables it
 * (enable requires IMPERSONATION_ENABLE_OTP from .env). Nested switch blocked.
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    public function __construct(
        private readonly SettingService $settings,
        private readonly Session $session,
    ) {}

    public function isEnabled(): bool
    {
        return $this->settings->boolean(SettingService::KEY_IMPERSONATION_ENABLED);
    }

    public function otpConfigured(): bool
    {
        return $this->configuredOtp() !== '';
    }

    public function enableWithOtp(string $otp): void
    {
        if (! $this->otpConfigured() || ! hash_equals($this->configuredOtp(), $otp)) {
            throw ImpersonationException::invalidEnableOtp();
        }

        $this->settings->set(SettingService::KEY_IMPERSONATION_ENABLED, '1');
    }

    public function disableFeature(): void
    {
        $this->settings->set(SettingService::KEY_IMPERSONATION_ENABLED, '0');
    }

    private function configuredOtp(): string
    {
        return (string) config('impersonation.enable_otp', '');
    }

    public function isImpersonating(): bool
    {
        return $this->session->has(self::SESSION_KEY);
    }

    public function actorMayStart(?User $actor): bool
    {
        return $actor !== null
            && $actor->isManager()
            && $this->isEnabled()
            && ! $this->isImpersonating();
    }

    public function canStart(User $actor, User $target): bool
    {
        return $this->actorMayStart($actor) && $actor->id !== $target->id;
    }

    public function start(User $actor, User $target): void
    {
        if (! $this->isEnabled()) {
            throw ImpersonationException::disabled();
        }

        if ($this->isImpersonating()) {
            throw ImpersonationException::alreadyImpersonating();
        }

        if ($actor->is($target)) {
            throw ImpersonationException::cannotImpersonateSelf();
        }

        $this->session->put(self::SESSION_KEY, $actor->id);
        Auth::login($target);
        $this->session->migrate(true);
    }

    public function stop(): User
    {
        $impersonatorId = $this->session->pull(self::SESSION_KEY);
        if ($impersonatorId === null) {
            throw ImpersonationException::notImpersonating();
        }

        $admin = User::query()->find($impersonatorId);
        if ($admin === null || ! $admin->isManager()) {
            Auth::logout();
            $this->session->invalidate();
            $this->session->regenerateToken();

            throw ImpersonationException::impersonatorMissing();
        }

        Auth::login($admin);
        $this->session->migrate(true);

        return $admin;
    }

    /**
     * @return array{name: string}|null
     */
    public function bannerState(?User $current): ?array
    {
        if ($current === null || ! $this->isImpersonating()) {
            return null;
        }

        return ['name' => $this->displayName($current)];
    }

    public function displayName(User $user): string
    {
        $preferred = app()->getLocale() === 'ar' ? trim((string) $user->name_ar) : trim((string) $user->name_en);
        $fallback = app()->getLocale() === 'ar' ? trim((string) $user->name_en) : trim((string) $user->name_ar);

        if ($preferred !== '') {
            return $preferred;
        }

        return $fallback !== '' ? $fallback : $user->email;
    }
}
