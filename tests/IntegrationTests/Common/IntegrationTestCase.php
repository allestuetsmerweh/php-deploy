<?php

declare(strict_types=1);

namespace PhpDeploy\Tests\IntegrationTests\Common;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class IntegrationTestCase extends TestCase {
    private $test_server_process;
    private $pipes;

    protected function setUp(): void {
        date_default_timezone_set('UTC');
        $tmp_path = __DIR__.'/../tmp';
        $this->removeRecursive($tmp_path);
        mkdir($tmp_path);
    }

    private function removeRecursive(string $path): void {
        if (is_dir($path)) {
            $entries = scandir($path);
            foreach ($entries as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $entry_path = realpath("{$path}/{$entry}");
                    $this->removeRecursive($entry_path);
                }
            }
            rmdir($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    protected function tearDown(): void {
        if ($this->test_server_process !== null) {
            stream_set_blocking($this->pipes[1], false);
            stream_set_blocking($this->pipes[2], false);
            echo "Stopping server...\n";
            $this->stopTestServer();
            echo "Stopped server.\n";
            echo "\n";
            echo "STDOUT:\n";
            echo fread($this->pipes[1], 1024 * 1024);
            echo "\n";
            echo "STDERR:\n";
            echo fread($this->pipes[2], 1024 * 1024);
            echo "\n\n";
            $this->test_server_process = null;
            $this->pipes = null;
        }
    }

    protected function startTestServer(
        $host = '127.0.0.1',
        $port = 8080,
        $path = null
    ) {
        $is_server_up = $this->isServerUp("http://{$host}:{$port}/is_server_up.html");
        if ($is_server_up) {
            throw new \Exception("A server instance is already running!");
        }
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
            $this->pipes,
        );
        $is_server_up_path = "{$path}/is_server_up.html";
        file_put_contents($is_server_up_path, 'true');
        if (is_resource($this->test_server_process)) {
            for ($i = 0; $i < 3; $i++) {
                try {
                    $is_server_up = $this->isServerUp("http://{$host}:{$port}/is_server_up.html");
                    if ($is_server_up) {
                        unlink($is_server_up_path);
                        echo "Started server.\n";
                        return;
                    }
                    echo "Server is not up yet.\n";
                } catch (\Exception $exc) {
                    echo "EXCEPTION({$i}): {$exc}\n";
                }
                sleep(1);
            }
        }
        unlink($is_server_up_path);
        echo "Could not start server.\n";
    }

    protected function stopTestServer() {
        proc_terminate($this->test_server_process);
        for ($i = 0; $i < 3; $i++) {
            $status = proc_get_status($this->test_server_process);
            $is_still_running = $status['running'] ?? false;
            if (!$is_still_running) {
                return;
            }
            echo "Server is still running.\n";
            sleep(1);
        }
        echo "Could not stop test server.\n";
    }

    protected function isServerUp($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        return curl_errno($ch) === 0 && curl_exec($ch) === 'true';
    }

    public function testDummy(): void {
        $this->assertSame(1, 1);
    }
}
