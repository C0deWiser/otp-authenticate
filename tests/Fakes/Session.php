<?php

namespace Codewiser\Otp\Tests\Fakes;

use Illuminate\Contracts\Session\Session as SessionContract;

class Session implements SessionContract
{
    public array $data = [];

    public function getName(): string
    {
        return 'test';
    }

    public function setName($name): void
    {
        //
    }

    public function getId(): string
    {
        return 'test-id';
    }

    public function setId($id): void
    {
        //
    }

    public function start(): bool
    {
        return true;
    }

    public function save(): void
    {
        //
    }

    public function all(): array
    {
        return $this->data;
    }

    public function exists($key)
    {
        return $this->has($key);
    }

    public function has($key)
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $item) {
            if (! array_key_exists($item, $this->data) || $this->data[$item] === null) {
                return false;
            }
        }

        return true;
    }

    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);

        $this->forget($key);

        return $value;
    }

    public function put($key, $value = null): void
    {
        if (is_array($key)) {
            $this->data = array_replace($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
    }

    public function flash(string $key, $value = true): void
    {
        $this->put($key, $value);
    }

    public function token(): string
    {
        return 'test-token';
    }

    public function regenerateToken(): void
    {
        //
    }

    public function remove($key)
    {
        $value = $this->get($key);

        $this->forget($key);

        return $value;
    }

    public function forget($keys): void
    {
        foreach ((array) $keys as $key) {
            unset($this->data[$key]);
        }
    }

    public function flush(): void
    {
        $this->data = [];
    }

    public function invalidate(): bool
    {
        $this->flush();

        return true;
    }

    public function regenerate($destroy = false): bool
    {
        return true;
    }

    public function migrate($destroy = false): bool
    {
        return true;
    }

    public function isStarted(): bool
    {
        return true;
    }

    public function previousUrl(): ?string
    {
        return null;
    }

    public function setPreviousUrl($url): void
    {
        //
    }

    public function getHandler(): mixed
    {
        return null;
    }

    public function handlerNeedsRequest(): bool
    {
        return false;
    }

    public function setRequestOnHandler($request): void
    {
        //
    }
}