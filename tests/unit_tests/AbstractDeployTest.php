<?php

declare(strict_types=1);

use PhpDeploy\AbstractDeploy;

require_once __DIR__.'/_common/UnitTestCase.php';

class FakeFlysystemFilesystem {
    public function writeStream($path, $stream) {
        $content = fread($stream, 1024 * 1024);
        file_put_contents(__DIR__."/tmp/remote/{$path}", $content);
    }

    public function createDirectory($path) {
        if (!is_dir($path)) {
            mkdir(__DIR__."/tmp/remote/{$path}");
        } else {
            throw new FilesystemException("Directory already exists");
        }
    }
}

class FakeDeploy extends AbstractDeploy {
    public function getLocalTmpDir() {
        return __DIR__.'/tmp';
    }

    protected function populateFolder() {
        $path = $this->getLocalBuildFolderPath();
        file_put_contents("{$path}/test.txt", 'test 1234');
    }

    protected function getFlysystemFilesystem() {
        return new FakeFlysystemFilesystem();
    }

    public function getRemotePublicPath() {
        $public_html_path = __DIR__."/tmp/remote/public_html";
        if (!is_dir($public_html_path)) {
            mkdir($public_html_path, 0777, true);
        }
        return 'public_html/';
    }

    public function getRemotePublicUrl() {
        $realpath = realpath(__DIR__.'/../../lib/remote_deploy.php');
        return "file://{$realpath}";
    }

    protected function getRandomPathComponent() {
        return 'deterministically-random';
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
        $this->assertSame(true, is_dir(dirname($local_zip_path)));
        $this->assertSame(true, is_file($local_zip_path));

        $zip = new ZipArchive();
        $res = $zip->open($local_zip_path);
        $this->assertSame(true, $res);
        $this->assertSame(true, $zip->locateName('test.txt') !== false);
        $this->assertSame(false, $zip->locateName('test') !== false);

        $this->assertSame(true, is_file("{$remote_base_path}{$remote_zip_path}"));
        $this->assertSame(true, is_file("{$remote_base_path}{$remote_script_path}"));
    }
}
