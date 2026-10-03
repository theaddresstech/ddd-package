<?php

namespace theaddresstechnology\DDD\Modules;

class StatusMigration
{
    public static function convert(string $legacyFile): array
    {
        if (!is_file($legacyFile)) {
            throw new \InvalidArgumentException('Legacy module status file was not found.');
        }

        $data = include $legacyFile;

        if (!is_array($data)) {
            throw new \UnexpectedValueException('Legacy module statuses must be an array.');
        }

        if (isset($data['modules']) && is_array($data['modules'])) {
            $data = $data['modules'];
        }

        $statuses = [];
        foreach ($data as $name => $value) {
            if (!is_string($name) || $name === '') {
                throw new \UnexpectedValueException('Invalid legacy module name.');
            }
            if (is_array($value)) {
                $value = $value['active'] ?? $value['enabled'] ?? null;
            }
            if (!is_bool($value) && $value !== 0 && $value !== 1) {
                throw new \UnexpectedValueException('Legacy module states must be booleans or 0/1.');
            }
            $statuses[$name] = (bool) $value;
        }

        return $statuses;
    }
}
