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
    public function createDirectory($path) {
        if (!is_dir($path)) {
            mkdir(__DIR__."/tmp/remote/{$path}");
        } else {
            throw new FilesystemException("Directory already exists");
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
    public function getLocalTmpDir() {
        return __DIR__.'/tmp';
    }

    protected function populateFolder() {
        $path = $this->getLocalBuildFolderPath();
        file_put_contents("{$path}/test.txt", 'test 1234');
        mkdir("{$path}/subdir/");
        file_put_contents("{$path}/subdir/subtest.txt", 'test 1234');
    }

    protected function getFlysystemFilesystem() {
        return new FakeFlysystemFilesystem();
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
        $private_path = __DIR__."/tmp/remote/private_files/deterministically-random";
        if (!is_dir($private_path)) {
            mkdir($private_path, 0777, true);
            file_put_contents("{$private_path}/deploy.php", 'test 1234');
        }
        return "private_files";
    }

    protected function getRandomPathComponent() {
        return 'deterministically-random';
    }

    public function testOnlyGetRandomPathComponent() {
        return parent::getRandomPathComponent();
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

        $this->assertSame(__DIR__.'/tmp/deterministically-random/', $local_folder_path);
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
    }

    public function testGetLocalBuildFolderPath(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->getLocalBuildFolderPath();
        $this->assertSame(__DIR__.'/tmp/deterministically-random/', $result);
    }

    public function testGetLocalZipPath(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->getLocalZipPath();
        $this->assertSame(__DIR__.'/tmp/deterministically-random.zip', $result);
    }

    public function testGetRemoteDeployPath(): void {
        $fake_deployment_builder = new FakeDeploy();

        $result = $fake_deployment_builder->getRemoteDeployPath();
        $this->assertSame('private_files/deploy/', $result);
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
}
