<?php

namespace Codewiser\Otp\Tests\Fakes;

use Codewiser\Otp\Contracts\MustVerifyEmailWithOtp;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class User implements Authenticatable, MustVerifyEmailWithOtp
{
    public int $id;

    public string $email = 'user@example.com';

    public string $username = 'johndoe';

    public array $sentOtps = [];

    public bool $emailVerified = false;

    public ?DateTimeInterface $emailVerifiedAt = null;

    public function __construct(int $id = 1)
    {
        $this->id = $id;
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

    public function getRememberTokenName(): ?string
    {
        return 'remember_token';
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->emailVerified;
    }

    public function shouldVerifyEmail(): bool
    {
        return ! $this->emailVerified;
    }

    public function markEmailAsVerified(): bool
    {
        $this->emailVerified = true;

        return true;
    }

    public function markEmailAsUnverified(): bool
    {
        $this->emailVerified = false;

        return true;
    }

    public function sendEmailVerificationNotification(): void
    {
        //
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }

    public function getEmailVerifiedAt(): ?DateTimeInterface
    {
        return $this->emailVerifiedAt;
    }

    public function sendOtpNotification(string $code): void
    {
        $this->sentOtps[] = $code;
    }
}