<?php

declare(strict_types=1);

namespace ImageBot;

use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;

final class FileCache implements CacheInterface
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return $default;
        }

        $payload = unserialize((string) file_get_contents($path));

        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            return $default;
        }

        if ($payload['expires'] !== null && $payload['expires'] < time()) {
            unlink($path);

            return $default;
        }

        return $payload['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $payload = serialize([
            'expires' => $this->expiresAt($ttl),
            'value' => $value,
        ]);

        return file_put_contents($this->path($key), $payload, LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);

        if (is_file($path)) {
            unlink($path);
        }

        return true;
    }

    public function clear(): bool
    {
        foreach (glob($this->directory.'/*.cache') ?: [] as $file) {
            unlink($file);
        }

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->get($key, $this) !== $this;
    }

    private function path(string $key): string
    {
        return $this->directory.'/'.hash('sha256', $key).'.cache';
    }

    private function expiresAt(null|int|DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof DateInterval) {
            return (new DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        return time() + $ttl;
    }
}
