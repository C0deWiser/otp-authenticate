<?php

namespace Codewiser\Otp\Tests\Fakes;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;

class Guard implements StatefulGuard
{
    public ?Authenticatable $user = null;

    public array $logins = [];

    public array $logouts = [];

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function id(): int|string|null
    {
        return $this->user?->getAuthIdentifier();
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    public function attempt(array $credentials = [], $remember = false): bool
    {
        return false;
    }

    public function once(array $credentials = []): bool
    {
        return false;
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        $this->logins[] = $user;

        $this->user = $user;
    }

    public function loginUsingId($id, $remember = false)
    {
        return null;
    }

    public function onceUsingId($id)
    {
        return null;
    }

    public function viaRemember(): bool
    {
        return false;
    }

    public function logout(): void
    {
        $this->logouts[] = $this->user;

        $this->user = null;
    }
}