<?php

namespace theaddresstechnology\DDD\Modules;

use theaddresstechnology\DDD\Helper\SafePath;

class Module
{
    public function __construct(
        private string $name,
        private string $path,
        private string $alias,
        private string $description,
        private array $keywords,
        private int $priority,
        private array $providers,
        private array $files,
        private bool $enabled,
    ) {
    }

    public static function fromManifest(string $path, array $manifest, bool $enabled): self
    {
        $name = SafePath::className((string) ($manifest['name'] ?? basename($path)));

        return new self(
            $name,
            $path,
            (string) ($manifest['alias'] ?? strtolower($name)),
            (string) ($manifest['description'] ?? ''),
            array_values(array_filter($manifest['keywords'] ?? [], 'is_string')),
            (int) ($manifest['priority'] ?? 0),
            array_values(array_filter($manifest['providers'] ?? [], 'is_string')),
            array_values(array_filter($manifest['files'] ?? [], 'is_string')),
            $enabled,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(string $relative = ''): string
    {
        if ($relative === '') {
            return $this->path;
        }

        $full = SafePath::confinedRelative($this->path, $relative);

        if ($full === null) {
            throw new \InvalidArgumentException('Invalid module path.');
        }

        return $full;
    }

    public function alias(): string
    {
        return $this->alias;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function keywords(): array
    {
        return $this->keywords;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function providers(): array
    {
        return $this->providers;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isDisabled(): bool
    {
        return !$this->enabled;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'name' => $this->name,
            'alias' => $this->alias,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'priority' => $this->priority,
            'providers' => $this->providers,
            'files' => $this->files,
            'path' => $this->path,
            default => $default,
        };
    }
}
