<?php

namespace theaddresstechnology\DDD\Modules\Activators;

class FileActivator implements Activator
{
    public function __construct(private string $statusesFile)
    {
    }

    public function all(): array
    {
        if (!file_exists($this->statusesFile) && !is_link($this->statusesFile)) {
            return [];
        }

        $contents = file_get_contents($this->statusesFile);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read module statuses.');
        }
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new \UnexpectedValueException('Module statuses must be an object of booleans.');
        }

        $statuses = [];
        foreach ($decoded as $name => $enabled) {
            if (!is_string($name) || !is_bool($enabled)) {
                throw new \UnexpectedValueException('Module statuses must be an object of booleans.');
            }
            $statuses[$name] = $enabled;
        }

        return $statuses;
    }

    public function get(string $name): ?bool
    {
        $statuses = $this->all();

        return array_key_exists($name, $statuses) ? $statuses[$name] : null;
    }

    public function set(string $name, bool $enabled): void
    {
        $this->update(function (array $statuses) use ($name, $enabled): array {
            $statuses[$name] = $enabled;

            return $statuses;
        });
    }

    public function delete(string $name): void
    {
        $this->update(function (array $statuses) use ($name): array {
            unset($statuses[$name]);

            return $statuses;
        });
    }

    public function replace(array $statuses): void
    {
        foreach ($statuses as $name => $enabled) {
            if (!is_string($name) || !is_bool($enabled)) {
                throw new \UnexpectedValueException('Module statuses must be an object of booleans.');
            }
        }
        $this->update(static fn (array $current): array => $statuses);
    }

    private function update(callable $update): void
    {
        $directory = dirname($this->statusesFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (is_link($this->statusesFile) || is_link($this->statusesFile.'.lock')) {
            throw new \RuntimeException('Module status files must not be symbolic links.');
        }
        $lock = fopen($this->statusesFile.'.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Unable to lock module statuses.');
        }
        $temporary = null;
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock module statuses.');
            }
            $contents = json_encode((object) $update($this->all()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            $temporary = tempnam($directory, '.ddd-status-');
            if ($temporary === false || file_put_contents($temporary, $contents) !== strlen($contents)) {
                throw new \RuntimeException('Unable to write module statuses.');
            }
            // Preserve permissions when replacing the existing file atomically.
            $permissions = is_file($this->statusesFile) ? (fileperms($this->statusesFile) & 0777) : 0644;
            if (!chmod($temporary, $permissions) || !rename($temporary, $this->statusesFile)) {
                throw new \RuntimeException('Unable to replace module statuses.');
            }
        } finally {
            if (is_string($temporary) && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
