<?php

declare(strict_types=1);

namespace PhpDeploy\Tests\UnitTests;

use PhpDeploy\AbstractDeploy;
use PhpDeploy\RemoteDeployLogger;
use PhpDeploy\Tests\Fake\FakeLogger;
use PhpDeploy\Tests\UnitTests\Common\UnitTestCase;

class FakeFlysystemEntry {
    public function __construct($path) {
        $this->path = $path;
    }

    public function path() {
        return $this->path;
    }
}

class FakeFlysystemFilesystem {
    public $has_thrown_create_directory_error = false;

    public function createDirectory($path) {
        $full_path = __DIR__."/tmp/remote/{$path}";
        if (!is_dir($full_path)) {
            mkdir($full_path);
        } else {
            $this->has_thrown_create_directory_error = true;
            throw new \Exception("Directory already exists");
        }
    }

    public function listContents($path) {
        return array_map(
            function ($name) {
                return new FakeFlysystemEntry($name);
            },
            scandir(__DIR__."/tmp/remote/{$path}"),
        );
    }

    public function writeStream($path, $stream) {
        $content = fread($stream, 1024 * 1024);
        $this->write($path, $content);
    }

    public function write($path, $content) {
        file_put_contents(__DIR__."/tmp/remote/{$path}", $content);
    }
}

class FakeDeploy extends AbstractDeploy {
    use \Psr\Log\LoggerAwareTrait;

    public $deploy_php_output;
    public $random_path_component = 'deterministically-random';
    public $installed_to;

    public function __construct() {
        $this->deploy_php_output = json_encode([
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
        ]);
    }

    public function getLocalTmpDir() {
        return __DIR__.'/tmp/local/local_tmp';
    }

    protected function populateFolder() {
        $path = $this->getLocalBuildFolderPath();
        file_put_contents("{$path}/test.txt", 'test 1234');
        mkdir("{$path}/subdir/");
        file_put_contents("{$path}/subdir/subtest.txt", 'test 1234');
    }

    protected function getFlysystemFilesystem() {
        if (!$this->flysystem_filesystem) {
            $this->flysystem_filesystem = new FakeFlysystemFilesystem();
        }
        return $this->flysystem_filesystem;
    }

    public function getRemotePublicPath() {
        $public_path = __DIR__."/tmp/remote/public_html";
        if (!is_dir($public_path)) {
            mkdir($public_path, 0777, true);
        }
        return 'public_html';
    }

    public function getRemotePublicUrl() {
        $realpath = realpath(__DIR__."/tmp/remote/private_files");
        return "file://{$realpath}";
    }

    public function getRemotePrivatePath() {
        $private_path = __DIR__."/tmp/remote/private_files/{$this->random_path_component}";
        if (!is_dir($private_path)) {
            mkdir($private_path, 0777, true);
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

    protected function getRandomPathComponent() {
        return $this->random_path_component;
    }

    public function install($public_path) {
        $this->installed_to = $public_path;
        return ['install_result' => 'fake-install-result'];
    }

    protected function afterDeploy($result) {
        $json_result = json_encode($result);
        $this->logger->info("afterDeploy {$json_result}");
    }

    public function testOnlyGetRandomPathComponent() {
        return parent::getRandomPathComponent();
    }

    public function testOnlyGetRemotePublicRandomDeployDirname() {
        return parent::getRemotePublicRandomDeployDirname();
    }

    public function testOnlyGetFlysystemFilesystem() {
        return $this->getFlysystemFilesystem();
    }

    public function testOnlyJustLogSomething() {
        return $this->logger->info('something');
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

        $fs = $fake_deployment_builder->testOnlyGetFlysystemFilesystem();
        $this->assertSame(false, $fs->has_thrown_create_directory_error);

        $this->assertSame([
            ['info', 'Build...', []],
            ['info', 'Populate build folder...', []],
            ['info', 'Zip build folder...', []],
            ['info', 'Zipping build folder...', []],
            ['info', 'Zipping done.', []],
            ['info', 'Build done.', []],
            ['info', 'Deploy...', []],
            ['info', 'Upload...', []],
            ['info', 'Upload done.', []],
            ['info', 'Running deploy script...', []],
            ['info', 'remote> 2009-02-13 23:31:30.000 something started...', []],
            ['info', 'Deploy done with result: {"deploy_result":"fake-deploy-result"}', []],
            ['info', 'afterDeploy {"deploy_result":"fake-deploy-result"}', []],
        ], $fake_logger->messages);
    }

    public function testBuildAndDeployWithRemoteError(): void {
        $fake_deployment_builder = new FakeDeploy();
        $fake_logger = new FakeLogger();
        $fake_deployment_builder->setLogger($fake_logger);
        $deploy_php_output = json_encode([
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
        ]);
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
                ['info', 'Upload...', []],
                ['info', 'Upload done.', []],
                ['info', 'Running deploy script...', []],
                ['error', 'remote> 2009-02-13 23:31:30.000 something failed...', []],
                ['invalid', 'remote> 2009-02-13 23:31:31.000 something is invalid...', []],
            ], $fake_logger->messages);
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
                ['info', 'Upload...', []],
                ['info', 'Upload done.', []],
                ['info', 'Running deploy script...', []],
            ], $fake_logger->messages);
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
                '/^Error invoking deploy script: /', $th->getMessage());
            $this->assertSame([
                ['info', 'Build...', []],
                ['info', 'Populate build folder...', []],
                ['info', 'Zip build folder...', []],
                ['info', 'Zipping build folder...', []],
                ['info', 'Zipping done.', []],
                ['info', 'Build done.', []],
                ['info', 'Deploy...', []],
                ['info', 'Upload...', []],
                ['info', 'Upload done.', []],
                ['info', 'Running deploy script...', []],
            ], $fake_logger->messages);
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
            mkdir($public_path, 0777, true);
        }

        $fake_deployment_builder->buildAndDeploy();

        $fs = $fake_deployment_builder->testOnlyGetFlysystemFilesystem();
        $this->assertSame(true, $fs->has_thrown_create_directory_error);

        $this->assertSame([
            ['info', 'Build...', []],
            ['info', 'Populate build folder...', []],
            ['info', 'Zip build folder...', []],
            ['info', 'Zipping build folder...', []],
            ['info', 'Zipping done.', []],
            ['info', 'Build done.', []],
            ['info', 'Deploy...', []],
            ['info', 'Upload...', []],
            ['info', 'Upload done.', []],
            ['info', 'Running deploy script...', []],
            ['info', 'remote> 2009-02-13 23:31:30.000 something started...', []],
            ['info', 'Deploy done with result: {"deploy_result":"fake-deploy-result"}', []],
            ['info', 'afterDeploy {"deploy_result":"fake-deploy-result"}', []],
        ], $fake_logger->messages);
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
            mkdir($public_path, 0777, true);
        }

        try {
            $fake_deployment_builder->testOnlyGetRemotePublicRandomDeployDirname();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertSame('Could not find a random directory to deploy to!', $th->getMessage());
        }
    }
}
