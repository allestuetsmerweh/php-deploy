<?php

declare(strict_types=1);

use PhpDeploy\AbstractDeploy;

require_once __DIR__.'/_common/IntegrationTestCase.php';

class FakeIntegrationDeploy extends AbstractDeploy {
    public function getLocalTmpDir() {
        $tmp_path = __DIR__.'/tmp/local_tmp';
        if (!is_dir($tmp_path)) {
            mkdir($tmp_path, 0777, true);
        }
        return $tmp_path;
    }

    protected function populateFolder() {
        $path = $this->getLocalBuildFolderPath();
        file_put_contents("{$path}/test.txt", 'test 1234');
        mkdir("{$path}/subdir/");
        file_put_contents("{$path}/subdir/subtest.txt", 'test 1234');
    }

    protected function getFlysystemFilesystem() {
        $adapter = new League\Flysystem\Local\LocalFilesystemAdapter(
            __DIR__.'/tmp/test_server/'
        );
        return new League\Flysystem\Filesystem($adapter);
    }

    public function getRemotePublicPath() {
        $public_path = __DIR__."/tmp/test_server/public_html";
        if (!is_dir($public_path)) {
            mkdir($public_path, 0777, true);
            file_put_contents("{$public_path}/index.php", 'some index stuff');
        }
        return 'public_html';
    }

    public function getRemotePublicUrl() {
        return "http://127.0.0.1:8081";
    }

    public function getRemotePrivatePath() {
        $private_deploy_path = __DIR__."/tmp/test_server/private_files/deploy";
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0777, true);
        }
        return 'private_files';
    }
}

/**
 * @internal
 * @covers \PhpDeploy\AbstractDeploy
 */
final class AbstractDeployIntegrationTest extends IntegrationTestCase {
    public function testBuildAndDeploy(): void {
        $path = __DIR__.'/tmp/test_server/public_html/';
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $this->startTestServer('127.0.0.1', 8081, $path);

        $fake_deployment_builder = new FakeIntegrationDeploy();

        $fake_deployment_builder->buildAndDeploy();

        $local_folder_path = $fake_deployment_builder->getLocalBuildFolderPath();
        $local_zip_path = $fake_deployment_builder->getLocalZipPath();
        $remote_base_path = __DIR__.'/tmp/test_server/';
        $remote_zip_path = $fake_deployment_builder->getRemoteZipPath();
        $remote_script_path = $fake_deployment_builder->getRemoteScriptPath();

        $this->assertMatchesRegularExpression('/\\/tmp\\/local_tmp\\/[\\S]{24}\\/$/', $local_folder_path);
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

        $this->assertSame(false, is_file("{$remote_base_path}/{$remote_zip_path}"));
        $this->assertSame(false, is_file("{$remote_base_path}/{$remote_script_path}"));
    }

    public function testGetLocalBuildFolderPath(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getLocalBuildFolderPath();
        $this->assertMatchesRegularExpression('/\\/tmp\\/local_tmp\\/[\\S]{24}\\/$/', $result);
    }

    public function testGetLocalZipPath(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getLocalZipPath();
        $this->assertMatchesRegularExpression('/\\/tmp\\/local_tmp\\/[\\S]{24}\.zip$/', $result);
    }

    public function testGetRemoteDeployPath(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getRemoteDeployPath();
        $this->assertSame('private_files/deploy/', $result);
    }

    public function testGetRemoteDeployDirname(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getRemoteDeployDirname();
        $this->assertSame('deploy', $result);
    }

    public function testGetRemoteZipPath(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getRemoteZipPath();
        $this->assertMatchesRegularExpression('/public_html\\/[\\S]{24}\\/deploy\\.zip$/', $result);
    }

    public function testGetRemoteScriptPath(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getRemoteScriptPath();
        $this->assertMatchesRegularExpression('/public_html\\/[\\S]{24}\\/deploy\\.php$/', $result);
    }
}
