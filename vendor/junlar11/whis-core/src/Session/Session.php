<?php

namespace Whis\Session;

use Throwable;

class Session
{
    public const FLASH_KEY = '_flash';

    protected SessionStorage $storage;
    private bool $closed = false;

    public function __construct(SessionStorage $storage)
    {
        $this->storage = $storage;
        $this->storage->start();

        if (! $this->storage->has(self::FLASH_KEY)) {
            $this->storage->set(self::FLASH_KEY, $this->emptyFlashData());
        } else {
            $this->storage->set(
                self::FLASH_KEY,
                $this->normalizeFlashData($this->storage->get(self::FLASH_KEY))
            );
        }
    }

    public function __destruct()
    {
        try {
            $this->close();
        } catch (Throwable) {
            // Nunca permitas que un destructor genere una segunda falla fatal.
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $flash = $this->normalizeFlashData($this->storage->get(self::FLASH_KEY));

        foreach ($flash['old'] as $key) {
            if (is_string($key)) {
                $this->storage->remove($key);
            }
        }

        $this->ageFlashData();
        $this->storage->save();
        $this->closed = true;
    }

    public function ageFlashData(): void
    {
        $flash = $this->normalizeFlashData($this->storage->get(self::FLASH_KEY));
        $flash['old'] = array_values(array_unique($flash['new']));
        $flash['new'] = [];

        $this->storage->set(self::FLASH_KEY, $flash);
    }

    public function flash(string $key, mixed $value): void
    {
        $this->storage->set($key, $value);

        $flash = $this->normalizeFlashData($this->storage->get(self::FLASH_KEY));
        $flash['new'][] = $key;
        $flash['new'] = array_values(array_unique($flash['new']));

        $this->storage->set(self::FLASH_KEY, $flash);
    }

    public function id(): string
    {
        return $this->storage->id();
    }

    public function regenerate(bool $deleteOldSession = true): bool
    {
        return $this->storage->regenerate($deleteOldSession);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->storage->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->storage->set($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->storage->has($key);
    }

    public function remove(string $key): void
    {
        $this->storage->remove($key);
    }

    public function destroy(): void
    {
        $this->storage->destroy();
        $this->closed = true;
    }

    private function emptyFlashData(): array
    {
        return ['old' => [], 'new' => []];
    }

    private function normalizeFlashData(mixed $flash): array
    {
        if (! is_array($flash)) {
            return $this->emptyFlashData();
        }

        return [
            'old' => is_array($flash['old'] ?? null) ? $flash['old'] : [],
            'new' => is_array($flash['new'] ?? null) ? $flash['new'] : [],
        ];
    }
}
