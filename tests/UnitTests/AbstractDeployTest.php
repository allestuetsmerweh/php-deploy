<?php

declare(strict_types=1);

namespace PhpDeploy\Tests\UnitTests;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PhpDeploy\AbstractDeploy;
use PhpDeploy\RemoteDeployLogger;
use PhpDeploy\Tests\Fake\FakeLogger;
use PhpDeploy\Tests\UnitTests\Common\UnitTestCase;

class FakeDeploy extends AbstractDeploy {
    use \Psr\Log\LoggerAwareTrait;

    protected ?Filesystem $flysystem_filesystem = null;
    public ?string $deploy_php_output;
    public string $random_path_component = 'deterministically-random';
    public ?string $installed_to;

    public function __construct() {
        $this->deploy_php_output = strval(json_encode([
            'success' => true,
            'result' => [
                'deploy_result' => 'fake-deploy-result',
            ],
            'log' => [
                [
                    'level' => 'info',
                    'timestamp' => 1234567890,
                    'message' => 'something started...',
                    'context' => [],
                ],
            ],
        ]));
    }

    public function getLocalTmpDir(): string {
        return __DIR__.'/tmp/local/local_tmp';
    }

    protected function populateFolder(): void {
        $path = $this->getLocalBuildFolderPath();
        file_put_contents("{$path}/test.txt", 'test 1234');
        mkdir("{$path}/subdir/");
        file_put_contents("{$path}/subdir/subtest.txt", 'test 1234');
    }

    protected function getFlysystemFilesystem(): Filesystem {
        if (!$this->flysystem_filesystem) {
            $adapter = new LocalFilesystemAdapter(__DIR__."/tmp/remote/");
            $this->flysystem_filesystem = new Filesystem($adapter);
        }
        return $this->flysystem_filesystem;
    }

    public function getRemotePublicPath(): string {
        $public_path = __DIR__."/tmp/remote/public_html";
        if (!is_dir($public_path)) {
            mkdir($public_path, 0o777, true);
        }
        return 'public_html';
    }

    public function getRemotePublicUrl(): string {
        $realpath = realpath(__DIR__."/tmp/remote/private_files");
        return "file://{$realpath}";
    }

    public function getRemotePrivatePath(): string {
        $private_path = __DIR__."/tmp/remote/private_files/{$this->random_path_component}";
        if (!is_dir($private_path)) {
            mkdir($private_path, 0o777, true);
            if ($this->deploy_php_output) {
                file_put_contents("{$private_path}/deploy.php", $this->deploy_php_output);
            } else {
                if (is_file("{$private_path}/deploy.php")) {
                    unlink("{$private_path}/deploy.php");
                }
            }
        }
        return "private_files";
    }

    protected function getRandomPathComponent(): string {
        return $this->random_path_component;
    }

    /** @return array<string, string> */
    public function install(string $public_path): array {
        $this->installed_to = $public_path;
        return ['install_result' => 'fake-install-result'];
    }

    protected function afterDeploy(array $result): void {
        $json_result = json_encode($result);
        $this->logger?->info("afterDeploy {$json_result}");
    }

    public function testOnlyHumanFileSize(int $size, string $unit = ''): string {
        return parent::humanFileSize($size, $unit);
    }

    public function testOnlyGetRandomPathComponent(): string {
        return parent::getRandomPathComponent();
    }

    public function testOnlyGetRemotePublicRandomDeployDirname(): string {
        return parent::getRemotePublicRandomDeployDirname();
    }

    public function testOnlyGetFlysystemFilesystem(): Filesystem {
        return $this->getFlysystemFilesystem();
    }

    public function testOnlyJustLogSomething(): void {
        $this->logger?->info('something');
    }
}

/**
 * @internal
 *
 * @covers \PhpDeploy\AbstractDeploy
 */
final class AbstractDeployTest extends UnitTestCase {
    public function testInjectRemoteLogger(): void {
        $remote_deploy_logger = new RemoteDeployLogger();
        $fake_deployment_builder = new FakeDeploy();
        $fake_deployment_builder->injectRemoteLogger($remote_deploy_logger);
        $fake_deployment_builder->testOnlyJustLogSomething();
        $this->assertSame([
            '[info] something',
        ], array_map(function ($message) {
            $level = $message['level'];
            $message = $message['message'];
            return "[{$level}] {$message}";
        }, $remote_deploy_logger->messages));
    }

    public function testInjectArgs(): void {
        $fake_deployment_builder = new FakeDeploy();
        $fake_deployment_builder->injectArgs(['remote_public_random_deploy_dirname' => 'test']);

        $this->assertSame('test', $fake_deployment_builder->testOnlyGetRemotePublicRandomDeployDirname());
    }

    public function testBuildAndDeploy(): void {
        $fake_deployment_builder = new FakeDeploy();
        $fake_logger = new FakeLogger();
        $fake_deployment_builder->setLogger($fake_logger);

        $fake_deployment_builder->buildAndDeploy();

        $local_folder_path = $fake_deployment_builder->getLocalBuildFolderPath();
        $local_zip_path = $fake_deployment_builder->getLocalZipPath();
        $remote_base_path = __DIR__.'/tmp/remote/';
        $remote_zip_path = $fake_deployment_builder->getRemoteZipPath();
        $remote_script_path = $fake_deployment_builder->getRemoteScriptPath();

        $this->assertSame(__DIR__.'/tmp/local/local_tmp/deterministically-random/', $local_folder_path);
        $this->assertSame(true, is_dir($local_folder_path));
        $this->assertSame(true, is_file("{$local_folder_path}/test.txt"));
        $this->assertSame(true, is_dir("{$local_folder_path}/subdir/"));
        $this->assertSame(true, is_file("{$local_folder_path}/subdir/subtest.txt"));
        $this->assertSame(true, is_dir(dirname($local_zip_path)));
        $this->assertSame(true, is_file($local_zip_path));

        $zip = new \ZipArchive();
        $res = $zip->open($local_zip_path);
        $this->assertSame(true, $res);
        $this->assertSame(true, $zip->locateName('test.txt') !== false);
        $this->assertSame(true, $zip->locateName('subdir/subtest.txt') !== false);
        $this->assertSame(false, $zip->locateName('test') !== false);

        $this->assertSame(true, is_file("{$remote_base_path}{$remote_zip_path}"));
        $this->assertSame(true, is_file("{$remote_base_path}{$remote_script_path}"));

        $this->assertSame([
            ['info', 'Build...', []],
            ['info', 'Populate build folder...', []],
            ['info', 'Zip build folder...', []],
            ['info', 'Zipping build folder...', []],
            ['info', 'Zipping done.', []],
            ['info', 'Build done.', []],
            ['info', 'Deploy...', []],
            ['info', 'Upload (244 bytes)...', []],
            ['info', 'Upload done.', []],
            ['info', 'Running deploy script (file://***/tmp/remote/private_files/deterministically-random/deploy.php)...', []],
            ['info', 'remote> 2009-02-13 23:31:30.000 something started...', []],
            ['info', 'Deploy done with result: {"deploy_result":"fake-deploy-result"}', []],
            ['info', 'afterDeploy {"deploy_result":"fake-deploy-result"}', []],
        ], array_map(function ($entry) {
            return [$entry[0], str_replace(__DIR__, '***', strval($entry[1])), $entry[2]];
        }, $fake_logger->messages));
    }

    public function testBuildAndDeployWithRemoteError(): void {
        $fake_deployment_builder = new FakeDeploy();
        $fake_logger = new FakeLogger();
        $fake_deployment_builder->setLogger($fake_logger);
        $deploy_php_output = strval(json_encode([
            'error' => [
                'type' => 'Exception',
                'message' => 'some exception',
            ],
            'log' => [
                [
                    'level' => 'error',
                    'timestamp' => 1234567890,
                    'message' => 'something failed...',
                    'context' => [],
                ],
                [
                    'level' => 'invalid',
                    'timestamp' => 1234567891,
                    'message' => 'something is invalid...',
                    'context' => [],
                ],
            ],
        ]));
        $fake_deployment_builder->deploy_php_output = $deploy_php_output;

        try {
            $fake_deployment_builder->buildAndDeploy();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertSame("Deployment failed: {$deploy_php_output}", $th->getMessage());

            $this->assertSame([
                ['info', 'Build...', []],
                ['info', 'Populate build folder...', []],
                ['info', 'Zip build folder...', []],
                ['info', 'Zipping build folder...', []],
                ['info', 'Zipping done.', []],
                ['info', 'Build done.', []],
                ['info', 'Deploy...', []],
                ['info', 'Upload (244 bytes)...', []],
                ['info', 'Upload done.', []],
                ['info', 'Running deploy script (file://***/tmp/remote/private_files/deterministically-random/deploy.php)...', []],
                ['error', 'remote> 2009-02-13 23:31:30.000 something failed...', []],
                ['invalid', 'remote> 2009-02-13 23:31:31.000 something is invalid...', []],
            ], array_map(function ($entry) {
                return [$entry[0], str_replace(__DIR__, '***', strval($entry[1])), $entry[2]];
            }, $fake_logger->messages));
        }
    }

    public function testBuildAndDeployWithRemoteNonJsonError(): void {
        $fake_deployment_builder = new FakeDeploy();
        $fake_logger = new FakeLogger();
        $fake_deployment_builder->setLogger($fake_logger);
        $deploy_php_output = 'non-JSON exception!';
        $fake_deployment_builder->deploy_php_output = $deploy_php_output;

        try {
            $fake_deployment_builder->buildAndDeploy();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertSame("Deployment failed: {$deploy_php_output}", $th->getMessage());

            $this->assertSame([
                ['info', 'Build...', []],
                ['info', 'Populate build folder...', []],
                ['info', 'Zip build folder...', []],
                ['info', 'Zipping build folder...', []],
                ['info', 'Zipping done.', []],
                ['info', 'Build done.', []],
                ['info', 'Deploy...', []],
                ['info', 'Upload (244 bytes)...', []],
                ['info', 'Upload done.', []],
                ['info', 'Running deploy script (file://***/tmp/remote/private_files/deterministically-random/deploy.php)...', []],
            ], array_map(function ($entry) {
                return [$entry[0], str_replace(__DIR__, '***', strval($entry[1])), $entry[2]];
            }, $fake_logger->messages));
        }
    }

    public function testBuildAndDeployWithRemoteFatalError(): void {
        $fake_deployment_builder = new FakeDeploy();
        $fake_logger = new FakeLogger();
        $fake_deployment_builder->setLogger($fake_logger);
        $fake_deployment_builder->deploy_php_output = null;

        try {
            $fake_deployment_builder->buildAndDeploy();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertMatchesRegularExpression(
                '/^Error invoking deploy script: /',
                $th->getMessage()
            );
            $this->assertSame([
                ['info', 'Build...', []],
                ['info', 'Populate build folder...', []],
                ['info', 'Zip build folder...', []],
                ['info', 'Zipping build folder...', []],
                ['info', 'Zipping done.', []],
                ['info', 'Build done.', []],
                ['info', 'Deploy...', []],
                ['info', 'Upload (244 bytes)...', []],
                ['info', 'Upload done.', []],
                ['info', 'Running deploy script (file://***/tmp/remote/private_files/deterministically-random/deploy.php)...', []],
            ], array_map(function ($entry) {
                return [$entry[0], str_replace(__DIR__, '***', strval($entry[1])), $entry[2]];
            }, $fake_logger->messages));
        }
    }

    public function testDeployRemoteDeployDirectoryAlreadyExists(): void {
        $fake_deployment_builder = new FakeDeploy();
        $fake_logger = new FakeLogger();
        $fake_deployment_builder->setLogger($fake_logger);
        $remote_zip_path = $fake_deployment_builder->getRemoteZipPath();
        $remote_path = __DIR__."/tmp/remote";
        $public_path = dirname("{$remote_path}/{$remote_zip_path}");
        if (!is_dir($public_path)) {
            mkdir($public_path, 0o777, true);
        }

        $fake_deployment_builder->buildAndDeploy();

        $this->assertSame([
            ['info', 'Build...', []],
            ['info', 'Populate build folder...', []],
            ['info', 'Zip build folder...', []],
            ['info', 'Zipping build folder...', []],
            ['info', 'Zipping done.', []],
            ['info', 'Build done.', []],
            ['info', 'Deploy...', []],
            ['info', 'Upload (244 bytes)...', []],
            ['info', 'Upload done.', []],
            ['info', 'Running deploy script (file://***/tmp/remote/private_files/deterministically-random/deploy.php)...', []],
            ['info', 'remote> 2009-02-13 23:31:30.000 something started...', []],
            ['info', 'Deploy done with result: {"deploy_result":"fake-deploy-result"}', []],
            ['info', 'afterDeploy {"deploy_result":"fake-deploy-result"}', []],
        ], array_map(function ($entry) {
            return [$entry[0], str_replace(__DIR__, '***', strval($entry[1])), $entry[2]];
        }, $fake_logger->messages));
    }

    public function testHumanFileSize(): void {
        $fake_deployment_builder = new FakeDeploy();

        $this->assertSame("0 bytes", $fake_deployment_builder->testOnlyHumanFileSize(0));
        $this->assertSame("1 bytes", $fake_deployment_builder->testOnlyHumanFileSize(1));
        $this->assertSame("1'023 bytes", $fake_deployment_builder->testOnlyHumanFileSize(1023));
        $this->assertSame("1.00 KB", $fake_deployment_builder->testOnlyHumanFileSize(1024));
        $this->assertSame("1'024.00 KB", $fake_deployment_builder->testOnlyHumanFileSize(1048575));
        $this->assertSame("1.00 MB", $fake_deployment_builder->testOnlyHumanFileSize(1048576));
        $this->assertSame("1'024.00 MB", $fake_deployment_builder->testOnlyHumanFileSize(1073741823));
        $this->assertSame("1.00 GB", $fake_deployment_builder->testOnlyHumanFileSize(1073741824));
    }

    public function testGetLocalBuildFolderPath(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->getLocalBuildFolderPath();
        $this->assertSame(__DIR__.'/tmp/local/local_tmp/deterministically-random/', $result);
    }

    public function testGetLocalZipPath(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->getLocalZipPath();
        $this->assertSame(__DIR__.'/tmp/local/local_tmp/deterministically-random.zip', $result);
    }

    public function testGetRemoteDeployPath(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->getRemoteDeployPath();
        $this->assertSame('private_files/deploy', $result);
    }

    public function testGetRemoteDeployDirname(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->getRemoteDeployDirname();
        $this->assertSame('deploy', $result);
    }

    public function testGetRemoteZipPath(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->getRemoteZipPath();
        $this->assertSame('public_html/deterministically-random/deploy.zip', $result);
    }

    public function testGetRemoteScriptPath(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->getRemoteScriptPath();
        $this->assertSame('public_html/deterministically-random/deploy.php', $result);
    }

    public function testGetRandomPathComponent(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->testOnlyGetRandomPathComponent();
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]{24}$/', $result);
    }

    public function testGetRemotePublicRandomDeployDirname(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->testOnlyGetRemotePublicRandomDeployDirname();
        $this->assertSame('deterministically-random', $result);
    }

    public function testGetRemotePublicRandomDeployDirnameAlreadyExists(): void {
        $fake_deployment_builder = new FakeDeploy();
        $fake_deployment_builder->random_path_component = 'already-exists';
        $public_path = __DIR__."/tmp/remote/public_html/already-exists";
        if (!is_dir($public_path)) {
            mkdir($public_path, 0o777, true);
        }

        try {
            $fake_deployment_builder->testOnlyGetRemotePublicRandomDeployDirname();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertSame('Could not find a random directory to deploy to!', $th->getMessage());
        }
    }
}
