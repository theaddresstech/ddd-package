<?php

namespace theaddresstechnology\DDD\Modules;

class StatusMigration
{
    public static function convert(string $legacyFile): array
    {
        if (!is_file($legacyFile)) {
            return [];
        }

        $data = include $legacyFile;

        if (!is_array($data)) {
            return [];
        }

        if (isset($data['modules']) && is_array($data['modules'])) {
            $data = $data['modules'];
        }

        $statuses = [];
        foreach ($data as $name => $value) {
            if (!is_string($name) || $name === '') {
                continue;
            }
            if (is_array($value)) {
                $statuses[$name] = (bool) ($value['active'] ?? $value['enabled'] ?? false);
                continue;
            }
            $statuses[$name] = (bool) $value;
        }

        return $statuses;
    }
}
