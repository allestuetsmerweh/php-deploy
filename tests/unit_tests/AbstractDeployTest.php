<?php

declare(strict_types=1);

use PhpDeploy\AbstractDeploy;

require_once __DIR__.'/_common/UnitTestCase.php';

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
    public $deploy_php_output = 'deploy:SUCCESS';
    public $random_path_component = 'deterministically-random';
    public $installed_to;

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
            file_put_contents("{$private_path}/deploy.php", $this->deploy_php_output);
        }
        return "private_files";
    }

    protected function getRandomPathComponent() {
        return $this->random_path_component;
    }

    public function install($public_path) {
        $this->installed_to = $public_path;
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
}

/**
 * @internal
 * @covers \PhpDeploy\AbstractDeploy
 */
final class AbstractDeployTest extends UnitTestCase {
    public function testBuildAndDeploy(): void {
        $fake_deployment_builder = new FakeDeploy();

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

        $zip = new ZipArchive();
        $res = $zip->open($local_zip_path);
        $this->assertSame(true, $res);
        $this->assertSame(true, $zip->locateName('test.txt') !== false);
        $this->assertSame(true, $zip->locateName('subdir/subtest.txt') !== false);
        $this->assertSame(false, $zip->locateName('test') !== false);

        $this->assertSame(true, is_file("{$remote_base_path}{$remote_zip_path}"));
        $this->assertSame(true, is_file("{$remote_base_path}{$remote_script_path}"));

        $fs = $fake_deployment_builder->testOnlyGetFlysystemFilesystem();
        $this->assertSame(false, $fs->has_thrown_create_directory_error);
    }

    public function testBuildAndDeployWithRemoteError(): void {
        $fake_deployment_builder = new FakeDeploy();
        $fake_deployment_builder->deploy_php_output = 'some exception';

        try {
            $fake_deployment_builder->buildAndDeploy();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertSame('Deployment failed: some exception', $th->getMessage());
        }
    }

    public function testDeployRemoteDeployDirectoryAlreadyExists(): void {
        $fake_deployment_builder = new FakeDeploy();
        $remote_zip_path = $fake_deployment_builder->getRemoteZipPath();
        $remote_path = __DIR__."/tmp/remote";
        $public_path = dirname("{$remote_path}/{$remote_zip_path}");
        if (!is_dir($public_path)) {
            mkdir($public_path, 0777, true);
        }

        $fake_deployment_builder->buildAndDeploy();

        $fs = $fake_deployment_builder->testOnlyGetFlysystemFilesystem();
        $this->assertSame(true, $fs->has_thrown_create_directory_error);
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
