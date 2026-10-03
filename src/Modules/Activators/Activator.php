<?php

namespace theaddresstechnology\DDD\Modules\Activators;

interface Activator
{
    public function all(): array;

    public function get(string $name): ?bool;

    public function set(string $name, bool $enabled): void;

    public function delete(string $name): void;
}
