<?php

namespace theaddresstechnology\DDD\Helper\Make\Types;

use theaddresstechnology\DDD\Helper\Make\Maker;
use theaddresstechnology\DDD\Helper\Make\Service\Test\TestCaseFactory;
use theaddresstechnology\DDD\Helper\Path;
use theaddresstechnology\DDD\Helper\SafePath;
use theaddresstechnology\DDD\Testing\PestTests;

class Test extends Maker
{
    public $options = ['domain', 'name', 'unit', 'feature', 'both', 'legacy-phpunit'];

    public $booleanOptions = ['unit', 'feature', 'both', 'legacy-phpunit'];

    public function getValues(): array
    {
        $domain = $this->command->option('domain');
        if (!$domain) {
            $domains = Path::getDomains();
            if ($domains === []) {
                throw new \InvalidArgumentException('Create a domain before generating its tests.');
            }
            $domain = $this->command->choice('Domain', $domains);
        }

        return [
            'domain' => $domain,
            'name' => $this->command->option('name') ?: SafePath::className($domain),
            'unit' => (bool) $this->command->option('unit'),
            'feature' => (bool) $this->command->option('feature'),
            'both' => (bool) $this->command->option('both'),
            'legacy-phpunit' => (bool) $this->command->option('legacy-phpunit'),
        ];
    }

    public function service(array $values): bool
    {
        try {
            $types = PestTests::types($values['unit'] ?? false, $values['feature'] ?? false, $values['both'] ?? false);
            $domain = SafePath::className($values['domain']);
            $root = Path::toDomain($domain);
            SafePath::confine($root, base_path());
            if (!is_dir($root)) {
                throw new \InvalidArgumentException('Domain does not exist.');
            }
            if ($values['legacy-phpunit'] ?? false) {
                if (($values['unit'] ?? false) || ($values['feature'] ?? false) || ($values['both'] ?? false)) {
                    throw new \InvalidArgumentException('--legacy-phpunit cannot be combined with Pest test type options.');
                }
                TestCaseFactory::generateEntities($this, $domain);
                TestCaseFactory::generateEntitiesRelations($this, $domain);
                TestCaseFactory::generateRepositoriesEloquent($this, $domain);
                TestCaseFactory::generateResources($this, $domain);
                return true;
            }
            $files = (new PestTests($root))->generate($values['name'] ?? $domain, $types, 'Tests');
            if ($this->command) {
                foreach ($files as $file) {
                    $this->command->info('Created '.$file);
                }
                $this->command->line('Run ddd:setup-tests once, then implement the generated Pest todos.');
            }
            return true;
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            if ($this->command) {
                $this->command->error($exception->getMessage());
                return false;
            }
            throw $exception;
        }
    }
}
