<?php

namespace theaddresstechnology\DDD\Testing;

use theaddresstechnology\DDD\Helper\SafePath;

class PestTests
{
    public function __construct(private string $root)
    {
        if (!is_dir($root)) {
            throw new \InvalidArgumentException('The domain or module directory does not exist.');
        }
    }

    public static function types(bool $unit, bool $feature, bool $both): array
    {
        if ((int) $unit + (int) $feature + (int) $both > 1) {
            throw new \InvalidArgumentException('Choose only one of --unit, --feature or --both.');
        }

        return $both ? ['Unit', 'Feature'] : [$unit ? 'Unit' : 'Feature'];
    }

    public function generate(string $name, array $types = ['Feature'], string $directory = 'tests', bool $preserveExisting = false): array
    {
        $parts = explode('/', str_replace('\\', '/', $name));
        foreach ($parts as $part) {
            if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $part)) {
                throw new \InvalidArgumentException('Use a test name such as OrderTest or Http/CreateOrderTest.');
            }
        }
        $name = implode('/', $parts);
        $name = str_ends_with($name, 'Test') ? $name : $name.'Test';
        $files = [];
        foreach (array_unique($types) as $type) {
            if (!in_array($type, ['Unit', 'Feature'], true)) {
                throw new \InvalidArgumentException('Test type must be Unit or Feature.');
            }
            $file = SafePath::confinedRelative($this->root, $directory.'/'.$type.'/'.$name.'.php');
            if ($file === null || is_link($file) || is_dir($file)) {
                throw new \InvalidArgumentException('Test output is unsafe or already exists. Existing tests are never overwritten.');
            }
            if (is_file($file) && $preserveExisting) {
                continue;
            }
            if (file_exists($file)) {
                throw new \InvalidArgumentException('Test output is unsafe or already exists. Existing tests are never overwritten.');
            }
            $stub = file_get_contents(__DIR__.'/../../stub/Testing/Pest'.$type.'.stub');
            if ($stub === false) {
                throw new \RuntimeException('Unable to read the Pest test template.');
            }
            $description = 'implements '.implode(' ', $parts).' '.strtolower($type).' behavior';
            $files[$file] = str_replace('{{DESCRIPTION}}', var_export($description, true), $stub);
        }
        // Preflight every destination before writing either half of --both.
        foreach ($files as $file => $contents) {
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0755, true)) {
                throw new \RuntimeException('Unable to create the test directory.');
            }
            $handle = fopen($file, 'x');
            if ($handle === false) {
                throw new \RuntimeException('Unable to create test without overwriting an existing file.');
            }
            try {
                if (fwrite($handle, $contents) !== strlen($contents)) {
                    throw new \RuntimeException('Unable to write the Pest test.');
                }
            } finally {
                fclose($handle);
            }
        }

        return array_keys($files);
    }
}
