<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class UnitTestCase extends TestCase {
    protected function setUp(): void {
        date_default_timezone_set('UTC');
        $tmp_path = __DIR__.'/../tmp';
        remove_r($tmp_path);
        mkdir($tmp_path);
    }

    public function testDummy(): void {
        $this->assertSame(1, 1);
    }
}

function remove_r($path) {
    if (is_dir($path)) {
        $entries = scandir($path);
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $entry_path = "{$path}/{$entry}";
                remove_r($entry_path);
            }
        }
        rmdir($path);
    } elseif (is_file($path)) {
        unlink($path);
    } elseif (is_link($path)) {
        unlink($path);
    }
}
