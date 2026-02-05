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
    /** @var ?resource */
    private mixed $test_server_process = null;
    /** @var ?array<resource> */
    private ?array $pipes = null;

    protected function setUp(): void {
        date_default_timezone_set('UTC');
        $tmp_path = __DIR__.'/../tmp';
        $this->removeRecursive($tmp_path);
        mkdir($tmp_path);
    }

    private function removeRecursive(string $path): void {
        if (is_dir($path)) {
            $entries = scandir($path);
            if ($entries) {
                foreach ($entries as $entry) {
                    if ($entry !== '.' && $entry !== '..') {
                        $entry_path = realpath("{$path}/{$entry}");
                        if ($entry_path) {
                            $this->removeRecursive($entry_path);
                        }
                    }
                }
            }
            rmdir($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }

    protected function tearDown(): void {
        if ($this->test_server_process !== null) {
            if ($this->pipes) {
                stream_set_blocking($this->pipes[1], false);
                stream_set_blocking($this->pipes[2], false);
            }
            echo "Stopping server...\n";
            $this->stopTestServer();
            echo "Stopped server.\n";
            if ($this->pipes) {
                echo "\n";
                echo "STDOUT:\n";
                echo fread($this->pipes[1], 1024 * 1024);
                echo "\n";
                echo "STDERR:\n";
                echo fread($this->pipes[2], 1024 * 1024);
            }
            echo "\n\n";
            $this->test_server_process = null;
            $this->pipes = null;
        }
    }

    protected function startTestServer(
        string $host = '127.0.0.1',
        int $port = 8080,
        ?string $path = null
    ): void {
        $is_server_up = $this->isServerUp("http://{$host}:{$port}/is_server_up.html");
        if ($is_server_up) {
            throw new \Exception("A server instance is already running!");
        }
        if ($path === null) {
            $path = __DIR__.'/../tmp/test-server/';
            if (!is_dir($path)) {
                mkdir($path, 0o777, true);
            }
        }
        echo "Starting server...\n";
        $php_path = system('which php');
        $descriptorspec = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        $proc = proc_open(
            "{$php_path} -S {$host}:{$port} -t {$path}",
            $descriptorspec,
            $this->pipes,
        );
        if (!$proc) {
            throw new \Exception("Could not proc_open.");
        }
        $this->test_server_process = $proc;
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

    protected function stopTestServer(): void {
        if (!$this->test_server_process) {
            return;
        }
        proc_terminate($this->test_server_process);
        for ($i = 0; $i < 3; $i++) {
            $status = proc_get_status($this->test_server_process);
            $is_still_running = $status['running'];
            if (!$is_still_running) {
                return;
            }
            echo "Server is still running.\n";
            sleep(1);
        }
        echo "Could not stop test server.\n";
    }

    protected function isServerUp(string $url): bool {
        $ch = curl_init($url);
        if (!$ch) {
            throw new \Exception('Could not create curl handle?!?');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        return curl_errno($ch) === 0 && curl_exec($ch) === 'true';
    }

    public function testDummy(): void {
        $this->assertSame(1, 1);
    }
}
