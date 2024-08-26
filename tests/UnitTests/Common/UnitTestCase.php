<?php

declare(strict_types=1);

namespace PhpDeploy\Tests\UnitTests\Common;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class UnitTestCase extends TestCase {
    protected function setUp(): void {
        date_default_timezone_set('UTC');
        $tmp_path = __DIR__.'/../tmp';
        $this->removeRecursive($tmp_path);
        mkdir($tmp_path);
    }

    protected function removeRecursive(string $path): void {
        if (is_dir($path)) {
            $entries = scandir($path);
            if ($entries) {
                foreach ($entries as $entry) {
                    if ($entry !== '.' && $entry !== '..') {
                        $entry_path = "{$path}/{$entry}";
                        $this->removeRecursive($entry_path);
                    }
                }
            }
            rmdir($path);
        } elseif (is_file($path)) {
            unlink($path);
        } elseif (is_link($path)) {
            unlink($path);
        }
    }

    public function testDummy(): void {
        $this->assertSame(1, 1);
    }
}
