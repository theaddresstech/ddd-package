<?php

namespace theaddresstechnology\DDD\Modules\Activators;

class FileActivator implements Activator
{
    public function __construct(private string $statusesFile)
    {
    }

    public function all(): array
    {
        if (!is_file($this->statusesFile)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->statusesFile), true);

        if (!is_array($decoded)) {
            return [];
        }

        $statuses = [];
        foreach ($decoded as $name => $enabled) {
            if (is_string($name)) {
                $statuses[$name] = (bool) $enabled;
            }
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
        $statuses = $this->all();
        $statuses[$name] = $enabled;
        $this->write($statuses);
    }

    public function delete(string $name): void
    {
        $statuses = $this->all();
        unset($statuses[$name]);
        $this->write($statuses);
    }

    private function write(array $statuses): void
    {
        $directory = dirname($this->statusesFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $this->statusesFile,
            json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );
    }
}
