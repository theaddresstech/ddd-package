<?php

namespace Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Src\Common\Helpers\UploadHelper;
use Tests\Support\GeneratedApplication;

class GeneratedUploadSecurityTest extends GeneratedApplication
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/ddd-upload-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/uploads', 0755, true);
        $this->app->usePublicPath($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_forged_extension_and_oversized_files_are_rejected_without_writes(): void
    {
        $forged = $this->upload('photo.jpg', '<script>alert(1)</script>');
        $this->assertSame('', UploadImages('avatars', $forged));
        $pdf = UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\n");
        $pdf->size(10241);
        $this->assertSame('', UploadImages('documents', $pdf));
        $this->assertSame([], File::allFiles($this->root.'/uploads'));
    }

    public function test_valid_content_gets_a_random_name_and_an_invalid_replacement_keeps_the_old_file(): void
    {
        $file = UploadedFile::fake()->createWithContent('original.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
        $path = UploadImages('documents', $file);
        $this->assertMatchesRegularExpression('/^documents\/[a-f0-9]{32}\.pdf$/', $path);
        $this->assertFileExists($this->root.'/uploads/'.$path);
        $forged = $this->upload('photo.jpg', 'not an image');
        $this->assertSame('', UploadHelper::UploadUpdate($path, 'documents', $forged));
        $this->assertFileExists($this->root.'/uploads/'.$path);
    }

    public function test_linked_upload_directories_cannot_write_or_delete_outside_the_upload_root(): void
    {
        mkdir($this->root.'/private');
        file_put_contents($this->root.'/private/secret.pdf', 'preserve');
        symlink($this->root.'/private', $this->root.'/uploads/linked');
        try {
            $pdf = UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\n");
            $this->assertSame('', UploadImages('linked', $pdf));
            UploadHelper::unlink('linked/secret.pdf');
            $this->assertFalse(UploadHelper::checkFile('linked/secret.pdf'));
            $this->assertSame('preserve', file_get_contents($this->root.'/private/secret.pdf'));
        } finally {
            unlink($this->root.'/uploads/linked');
        }
    }
    private function upload(string $name, string $contents): UploadedFile
    {
        $path = $this->root.'/source-'.bin2hex(random_bytes(4));
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, UPLOAD_ERR_OK, true);
    }

}
