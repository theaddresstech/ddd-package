<?php

namespace theaddresstechnology\DDD\Testing;

use theaddresstechnology\DDD\Helper\SafePath;

class PestSetup
{
    public function __construct(private string $root)
    {
    }

    /** Prepare default Laravel test support without replacing application-owned files. */
    public function install(string $modules = 'src/Domain'): array
    {
        if (SafePath::confinedRelative($this->root, $modules) === null) {
            throw new \InvalidArgumentException('The module root must be a relative path inside the application.');
        }
        $modules = trim(str_replace('\\', '/', $modules), '/');
        $xml = $this->path('phpunit.xml');
        if (!is_file($xml) && is_file($this->path('phpunit.xml.dist'))) {
            $xml = $this->path('phpunit.xml.dist');
        }
        $contents = is_file($xml) ? file_get_contents($xml) : file_get_contents(__DIR__.'/../../stub/Testing/phpunit.stub');
        if ($contents === false || stripos($contents, '<!DOCTYPE') !== false) {
            throw new \RuntimeException('Unable to read safe PHPUnit configuration.');
        }
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$document->loadXML($contents, LIBXML_NONET) || $document->documentElement?->tagName !== 'phpunit') {
                throw new \RuntimeException('Invalid PHPUnit XML; existing configuration was left unchanged.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $suites = $xpath->query('/phpunit/testsuites')->item(0);
        if ($suites === null) {
            $suites = $document->createElement('testsuites');
            $document->documentElement->insertBefore($suites, $document->documentElement->firstChild);
        }
        $messages = [];
        $existingModuleDiscovery = false;
        foreach ($xpath->query('/phpunit/testsuites/testsuite/directory') as $directory) {
            $relative = preg_replace('#^'.preg_quote(rtrim($this->root, '/').'/', '#').'#', '', trim($directory->textContent));
            $relative = preg_replace('#^(\./)+#', '', $relative);
            if ($relative === $modules || str_starts_with($relative, $modules.'/')
                || str_starts_with($modules.'/', rtrim($relative, '/').'/')) {
                $existingModuleDiscovery = true;
            }
        }
        if (!$existingModuleDiscovery) {
            foreach (['Unit', 'Feature'] as $type) {
                $suite = $document->createElement('testsuite');
                $suite->setAttribute('name', 'DDD '.$type);
                $directory = $document->createElement('directory');
                $directory->setAttribute('suffix', 'Test.php');
                $directory->appendChild($document->createTextNode($modules.'/*/[Tt]ests/'.$type));
                $suite->appendChild($directory);
                $suites->appendChild($suite);
            }
            $messages[] = 'Added discovery for module tests/ and legacy domain Tests/ directories.';
        } else {
            $messages[] = 'Preserved existing module test discovery. Check that it includes every desired Unit and Feature directory.';
        }

        $newFiles = [
            $this->path('tests/Pest.php') => "<?php\n\n// Shared Pest hooks and expectations belong here.\n// Generated feature files bind Tests\\TestCase individually; unit files stay isolated.\n",
            $this->path('tests/TestCase.php') => "<?php\n\nnamespace Tests;\n\nabstract class TestCase extends \\Illuminate\\Foundation\\Testing\\TestCase\n{\n}\n",
        ];
        // Validate all destinations, including existing files, before changing anything.
        foreach (['tests', 'tests/Unit', 'tests/Feature'] as $directory) {
            $path = $this->path($directory);
            if (file_exists($path) && !is_dir($path)) {
                throw new \RuntimeException('A test directory is occupied by a file.');
            }
        }
        foreach (array_keys($newFiles) as $file) {
            if (is_dir($file) || is_link($file)) {
                throw new \RuntimeException('Test configuration must be an ordinary file.');
            }
        }
        if (is_link($xml)) {
            throw new \RuntimeException('Refusing to replace a linked PHPUnit configuration.');
        }
        foreach (['tests/Unit', 'tests/Feature'] as $directory) {
            $path = $this->path($directory);
            if (!is_dir($path) && !mkdir($path, 0755, true)) {
                throw new \RuntimeException('Unable to create test directories.');
            }
        }
        foreach ($newFiles as $file => $content) {
            if (is_file($file)) {
                $messages[] = 'Preserved '.basename($file).'.';
                continue;
            }
            $handle = fopen($file, 'x');
            if ($handle === false) {
                throw new \RuntimeException('Unable to create test configuration without overwriting it.');
            }
            try {
                if (fwrite($handle, $content) !== strlen($content)) {
                    throw new \RuntimeException('Unable to write test configuration.');
                }
            } finally {
                fclose($handle);
            }
        }
        if (!$existingModuleDiscovery || !is_file($xml)) {
            $document->formatOutput = true;
            $updated = $document->saveXML();
            $temporary = tempnam($this->root, '.ddd-tests-');
            if ($temporary === false || $updated === false) {
                throw new \RuntimeException('Unable to prepare PHPUnit configuration.');
            }
            try {
                if (file_put_contents($temporary, $updated) !== strlen($updated)
                    || !chmod($temporary, is_file($xml) ? (fileperms($xml) & 0777) : 0644)
                    || !rename($temporary, $xml)) {
                    throw new \RuntimeException('Unable to save PHPUnit configuration.');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
        return $messages;
    }

    private function path(string $relative): string
    {
        return SafePath::confine(rtrim($this->root, '/').'/'.$relative, $this->root);
    }
}
