<?php

declare(strict_types=1);

namespace Framework\Session;

/**
 * Session wrapper with one-request flash data.
 *
 * Constructed with a plain array in tests; Session::start() binds it to
 * the native $_SESSION.
 */
final class Session
{
    private const FLASH_KEY = '_flash';

    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public static function start(): self
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
                'use_strict_mode' => true,
            ]);
        }

        $session = new self();
        $session->data = &$_SESSION;

        return $session;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Stores a value that will be available until it is next read.
     */
    public function flash(string $key, mixed $value): void
    {
        $this->data[self::FLASH_KEY][$key] = $value;
    }

    /**
     * Returns and clears all flashed values.
     *
     * @return array<string, mixed>
     */
    public function pullFlash(): array
    {
        $flash = $this->data[self::FLASH_KEY] ?? [];
        unset($this->data[self::FLASH_KEY]);

        return $flash;
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
