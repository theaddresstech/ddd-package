<?php

namespace theaddresstechnology\DDD\Helper;

class SafePath
{
    public static function segment(string $segment): string
    {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new \InvalidArgumentException('Invalid path segment.');
        }

        if (strpbrk($segment, "/\\\0") !== false) {
            throw new \InvalidArgumentException('Invalid path segment.');
        }

        return $segment;
    }

    public static function isIdentifier(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value);
    }

    public static function isRelation(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', $value);
    }

    public static function isRelativePath(string $value): bool
    {
        $value = str_replace('\\', '/', $value);

        if ($value === '' || str_contains($value, "\0") || str_starts_with($value, '/')) {
            return false;
        }

        $value = trim($value, '/');

        if ($value === '') {
            return false;
        }

        foreach (explode('/', $value) as $part) {
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*(\.[A-Za-z0-9]+)?$/', $part)) {
                return false;
            }
        }

        return true;
    }

    public static function isClassName(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value);
    }

    public static function isQualifiedName(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $value);
    }

    public static function queryOperator(string $operator): ?string
    {
        $operator = strtolower(trim($operator));
        $allowed = ['=', 'like', 'ilike', 'in', 'between', '>', '<', '>=', '<=', '<>', '!='];

        return in_array($operator, $allowed, true) ? $operator : null;
    }

    public static function sortDirection(string $direction): ?string
    {
        $direction = strtolower(trim($direction));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : null;
    }

    public static function className(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9]+/', ' ', $name) ?? '';
        $name = str_replace(' ', '', ucwords(strtolower(trim($name))));

        if (!self::isClassName($name) || !preg_match('/^[A-Za-z]/', $name)) {
            throw new \InvalidArgumentException('Invalid class name.');
        }

        return $name;
    }

    public static function tableName(string $name): string
    {
        $snake = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $name) ?? '');
        $snake = trim($snake, '_');

        if ($snake === '' || !preg_match('/^[a-z_][a-z0-9_]*$/', $snake)) {
            throw new \InvalidArgumentException('Invalid table name.');
        }

        return $snake;
    }

    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $absolute = str_starts_with($path, '/');
        $parts = [];

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }

        return ($absolute ? '/' : '').implode('/', $parts);
    }

    public static function isInside(string $path, string $root): bool
    {
        $path = self::normalize($path);
        $root = rtrim(self::normalize($root), '/');

        return $path === $root || str_starts_with($path, $root.'/');
    }

    public static function confine(string $path, string $root): string
    {
        if (!self::isInside($path, $root)) {
            throw new \InvalidArgumentException('Path escapes its root.');
        }

        return $path;
    }

    public static function confinedRelative(string $root, string $relative): ?string
    {
        $relative = str_replace('\\', '/', $relative);

        if (!self::isRelativePath($relative)) {
            return null;
        }

        $relative = trim($relative, '/');

        $full = rtrim(str_replace('\\', '/', $root), '/').'/'.$relative;

        if (!self::isInside($full, $root)) {
            return null;
        }

        return $full;
    }
}
