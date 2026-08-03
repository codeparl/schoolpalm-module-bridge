<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services\Host;

use SchoolPalm\ModuleBridge\Contracts\Host\UserHost;
use SchoolPalm\ModuleBridge\Support\Helper;
use App\Models\User;

/**
 * UserHostService
 *
 * Resolves the current user context for:
 * - SchoolPalm runtime (auth + tenancy)
 * - SDK runtime (fake JSON user data)
 */
class UserHostService implements UserHost
{
    protected ?array $sdkUser = null;

    /**
     * Get current user
     */
    public function current(): ?object
    {
        if (Helper::isSdkRuntime()) {
            return (object) $this->sdkUser();
        }

        $user = auth()->user();

        return $user ? (object) [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'login_type' => $user->login_type ?? null,
            'main_role_id' => $user->main_role_id ?? null,
            'current_portal' => $user->current_portal ?? null,
        ] : null;
    }

    /**
     * Get user ID
     */
    public function id(): ?int
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkUser()['id'] ?? 1;
        }

        return auth()->id();
    }

    /**
     * Get user email
     */
    public function email(): ?string
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkUser()['email'] ?? 'admin@sdk.test';
        }

        return auth()->user()?->email;
    }

    /**
     * Get roles
     */
    public function roles(): array
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkUser()['roles'] ?? ['admin'];
        }

        $user = auth()->user();

        if (!$user) {
            return [];
        }

        // adapt this to your role system if needed
        return $user->roles?->pluck('name')->toArray() ?? [];
    }

    /**
     * Get current portal
     */
    public function currentPortal(): ?string
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkUser()['current_portal'] ?? 'admin';
        }

        return auth()->user()?->current_portal;
    }

    /**
     * Load SDK user
     */
    protected function sdkUser(): array
    {
        if ($this->sdkUser !== null) {
            return $this->sdkUser;
        }

        $path = Helper::dataFolder('users/user.json');

        if (!file_exists($path)) {
            return $this->sdkUser = [
                'id' => 1,
                'name' => 'SDK Admin',
                'email' => 'admin@sdk.test',
                'roles' => ['admin'],
                'current_portal' => 'admin',
            ];
        }

        return $this->sdkUser = Helper::loadJson($path);
    }
}