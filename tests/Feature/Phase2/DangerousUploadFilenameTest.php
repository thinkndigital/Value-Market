<?php

namespace Tests\Feature\Phase2;

use Tests\TestCase;

/**
 * Phase 2 (Task 12, file upload security audit): Admin\MediaController::upload() and
 * Seller\MediaController::upload() - the generic "upload any file" endpoints backing the admin media
 * library - accepted any file type with no extension/mime validation at all. Confirmed reachable by
 * default (no StorageType row is seeded, so uploads always fall back to the 'public' disk -
 * storage/app/public, symlinked to public/storage under Apache's DocumentRoot with no PHP-execution
 * restriction - docker/apache-cloud-run.conf). A `.php` file uploaded through either endpoint was directly
 * executable via its public URL: remote code execution, and Seller's version is reachable by any seller
 * account, not just admin staff.
 *
 * isDangerousUploadFilename() (app/function_helper.php) is the actual security boundary now wired into
 * both controllers' upload() methods before any file is processed; this proves that function's behavior
 * directly rather than fighting the full request/permission/StoreService stack to exercise it indirectly.
 */
class DangerousUploadFilenameTest extends TestCase
{
    /** @dataProvider dangerousFilenames */
    public function test_dangerous_filenames_are_flagged(string $filename): void
    {
        $this->assertTrue(
            isDangerousUploadFilename($filename),
            "Expected \"$filename\" to be flagged as a dangerous upload."
        );
    }

    public static function dangerousFilenames(): array
    {
        return [
            'plain php' => ['shell.php'],
            'php variant extension' => ['shell.php5'],
            'phtml' => ['shell.phtml'],
            'double extension trick' => ['shell.php.jpg'],
            'double extension trick, uppercase' => ['SHELL.PHP.JPG'],
            'htaccess' => ['.htaccess'],
            'htpasswd' => ['.htpasswd'],
            'cgi script' => ['backdoor.cgi'],
            'shell script' => ['run.sh'],
            'windows executable' => ['virus.exe'],
            'python script' => ['exploit.py'],
        ];
    }

    /** @dataProvider legitimateFilenames */
    public function test_legitimate_filenames_are_not_flagged(string $filename): void
    {
        $this->assertFalse(
            isDangerousUploadFilename($filename),
            "Expected \"$filename\" to be allowed - it is a legitimate file type this app actually uses."
        );
    }

    public static function legitimateFilenames(): array
    {
        return [
            'jpeg image' => ['product-photo.jpg'],
            'png image' => ['banner.png'],
            'pdf document' => ['invoice.pdf'],
            'word document' => ['terms.docx'],
            'excel sheet' => ['bulk-upload.xlsx'],
            'csv' => ['export.csv'],
            'mp4 video' => ['demo.mp4'],
            'mp3 audio' => ['ringtone.mp3'],
            'zip archive' => ['assets.zip'],
            'filename with dots in the base name' => ['v1.2.3-release-notes.pdf'],
        ];
    }
}
