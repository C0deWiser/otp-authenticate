<?php

namespace Codewiser\Otp\Tests\Fakes;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;

class UserProvider implements UserProviderContract
{
    /**
     * @param  array<string, Authenticatable>  $users  Keyed by the credential value.
     */
    public function __construct(protected array $users = [], protected string $key = 'email')
    {
        //
    }

    public function retrieveById($identifier)
    {
        foreach ($this->users as $user) {
            if ($user->getAuthIdentifier() == $identifier) {
                return $user;
            }
        }

        return null;
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token)
    {
        //
    }

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        $value = $credentials[$this->key] ?? null;

        return $value !== null ? ($this->users[$value] ?? null) : null;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials)
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false)
    {
        //
    }
}
