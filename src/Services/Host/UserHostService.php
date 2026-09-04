<?php

declare(strict_types=1);

namespace SchoolPalm\ModuleBridge\Services\Host;

use SchoolPalm\ModuleBridge\Contracts\Host\UserHost;
use SchoolPalm\ModuleBridge\Support\ContextData;
use SchoolPalm\ModuleBridge\Support\Helper;

/**
 * UserHostService
 *
 * Resolves the current user context for:
 * - SchoolPalm runtime (auth + tenancy)
 * - SDK runtime (fake JSON user data)
 */
class UserHostService implements UserHost
{
    /**
     * Cached SDK user array
     */
    protected ?array $sdkUser = null;

    /**
     * Get current user array representation.
     */
    public function currentArray(): ?array
    {
        if (Helper::isSdkRuntime()) {
            return $this->sdkUserArray();
        }

        return $this->realUserArray();
    }

    /**
     * Get current user context.
     * Pass $asArray = true for queue job payloads and Blade view parameters.
     */
    public function current(bool $asArray = false): null|array|ContextData
    {
        $data = $this->currentArray();

        if ($data === null) {
            return null;
        }

        return $asArray ? $data : ContextData::make($data);
    }

    /**
     * Get user ID
     */
    public function id(): int|string|null
    {
        return $this->currentArray()['id'] ?? null;
    }

    /**
     * Get user email
     */
    public function email(): ?string
    {
        return $this->currentArray()['email'] ?? null;
    }

    /**
     * Get user roles
     */
    public function roles(): array
    {
        return $this->currentArray()['roles'] ?? [];
    }

    /**
     * Get current portal
     */
    public function currentPortal(): ?string
    {
        return $this->currentArray()['current_portal'] ?? null;
    }

    /**
     * Real user array representation
     */
    protected function realUserArray(): ?array
    {
        $user = auth()->user();

        if (!$user) {
            return null;
        }

        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'login_type'     => $user->login_type ?? null,
            'main_role_id'   => $user->main_role_id ?? null,
            'roles'          => method_exists($user, 'roles') && $user->roles ? $user->roles->pluck('name')->toArray() : [],
            'current_portal' => $user->current_portal ?? null,
        ];
    }

    /**
     * Load SDK user array
     */
    protected function sdkUserArray(): array
    {
        if ($this->sdkUser !== null) {
            return $this->sdkUser;
        }

        $path = Helper::dataFolder('users/user.json');

        if (!file_exists($path)) {
            return $this->sdkUser = [
                'id'             => 1,
                'name'           => 'SDK Admin',
                'email'          => 'admin@sdk.test',
                'roles'          => ['admin'],
                'current_portal' => 'admin',
            ];
        }

        return $this->sdkUser = Helper::loadJson($path) ?? [];
    }
}
