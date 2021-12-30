<?php

declare(strict_types=1);

use Nette\Utils\FileSystem;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
class IntegrationTestCase extends TestCase {
    private $test_server_process;

    protected function setUp(): void {
        date_default_timezone_set('UTC');
        $tmp_path = __DIR__.'/../tmp';
        FileSystem::delete($tmp_path);
        mkdir($tmp_path);
    }

    protected function tearDown(): void {
        if ($this->test_server_process !== null) {
            echo "Stopping server...\n";
            proc_terminate($this->test_server_process);
            echo "Stopped server.\n";
        }
    }

    protected function startTestServer(
        $host = '127.0.0.1',
        $port = 8080,
        $path = null
    ) {
        if ($path === null) {
            $path = __DIR__.'/../tmp/test-server/';
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
        echo "Starting server...\n";
        $php_path = system('which php');
        $descriptorspec = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        $this->test_server_process = proc_open(
            "{$php_path} -S {$host}:{$port} -t {$path}",
            $descriptorspec,
            $pipes,
        );
        $is_server_up_path = "{$path}/is_server_up.html";
        file_put_contents($is_server_up_path, 'true');
        if (is_resource($this->test_server_process)) {
            for ($i = 0; $i < 3; $i++) {
                try {
                    $is_server_up_content = file_get_contents(
                        "http://{$host}:{$port}/is_server_up.html");
                    if ($is_server_up_content === 'true') {
                        unlink($is_server_up_path);
                        echo "Started server.\n";
                        return;
                    }
                    echo "Wrong response: {$is_server_up_content}\n";
                } catch (\Exception $exc) {
                    echo "EXCEPTION({$i}): {$exc}\n";
                }
                sleep(1);
            }
        }
        unlink($is_server_up_path);
        echo "Could not start server.\n";
    }

    public function testDummy(): void {
        $this->assertSame(1, 1);
    }
}
