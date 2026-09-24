<?php

namespace Codewiser\Otp\Tests\Fakes;

use Illuminate\Contracts\Auth\Authenticatable;

class PlainUser implements Authenticatable
{
    public function __construct(public int $id)
    {
        //
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int|string|null
    {
        return $this->id;
    }

    public function getAuthPassword(): ?string
    {
        return null;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}