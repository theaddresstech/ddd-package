<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use theaddresstechnology\DDD\Helper\SafePath;

class SafePathTest extends TestCase
{
    public function test_segment_rejects_traversal(): void
    {
        foreach (['..', '.', '', 'foo/bar', "foo\0bar", 'foo\\bar'] as $segment) {
            try {
                SafePath::segment($segment);
                $this->fail('Expected invalid segment: '.$segment);
            } catch (\InvalidArgumentException $e) {
                $this->assertSame('Invalid path segment.', $e->getMessage());
            }
        }
    }

    public function test_segment_accepts_a_file_name(): void
    {
        $this->assertSame('User.php', SafePath::segment('User.php'));
    }

    public function test_confine_rejects_a_sibling_prefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SafePath::confine('/var/www-evil/src', '/var/www');
    }

    public function test_confine_accepts_a_child_path(): void
    {
        $this->assertSame('/var/www/src/Domain', SafePath::confine('/var/www/src/Domain', '/var/www'));
    }

    public function test_relative_upload_path_rejects_escape(): void
    {
        $this->assertNull(SafePath::confinedRelative('/var/www/public/uploads', '../.env'));
        $this->assertNull(SafePath::confinedRelative('/var/www/public/uploads', '/etc/passwd'));
        $this->assertSame(
            '/var/www/public/uploads/avatars/photo.png',
            SafePath::confinedRelative('/var/www/public/uploads', 'avatars/photo.png')
        );
    }

    public function test_query_operator_allowlist(): void
    {
        $this->assertSame('like', SafePath::queryOperator(' LIKE '));
        $this->assertNull(SafePath::queryOperator('whereRaw'));
        $this->assertSame('asc', SafePath::sortDirection('ASC'));
        $this->assertNull(SafePath::sortDirection('asc;drop'));
    }

    public function test_class_and_table_names_strip_path_characters(): void
    {
        $this->assertSame('EtcPasswd', SafePath::className('../../etc/passwd'));
        $this->assertSame('etc_passwd', SafePath::tableName('../../etc/passwd'));
    }

    public function test_empty_class_name_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SafePath::className('...');
    }

    public function test_class_names_preserve_existing_camel_case(): void
    {
        $this->assertSame('SalesReport', SafePath::className('SalesReport'));
        $this->assertSame('SalesReport', SafePath::className('sales report'));
    }
}
