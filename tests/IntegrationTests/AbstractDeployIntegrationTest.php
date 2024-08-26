<?php

declare(strict_types=1);

namespace PhpDeploy\Tests\IntegrationTests;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PhpDeploy\AbstractDeploy;
use PhpDeploy\Tests\Fake\FakeLogger;
use PhpDeploy\Tests\IntegrationTests\Common\IntegrationTestCase;

class FakeIntegrationDeploy extends AbstractDeploy {
    use \Psr\Log\LoggerAwareTrait;

    public int $port = 8081;

    public function getLocalTmpDir(): string {
        $tmp_path = __DIR__.'/tmp/local/local_tmp';
        if (!is_dir($tmp_path)) {
            mkdir($tmp_path, 0o777, true);
        }
        return $tmp_path;
    }

    protected function populateFolder(): void {
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $path = $this->getLocalBuildFolderPath();

        $fs->copy(__DIR__.'/resources/DependentDeploy.php', "{$path}/Deploy.php", true);
        $fs->mirror(__DIR__.'/../../vendor', "{$path}/vendor");
        file_put_contents("{$path}/test.txt", 'test1234');
        $fs->mkdir("{$path}/subdir/");
        file_put_contents("{$path}/subdir/subtest.txt", 'subtest1234');
    }

    protected function getFlysystemFilesystem(): Filesystem {
        $adapter = new LocalFilesystemAdapter(
            __DIR__.'/tmp/test_server/'
        );
        return new Filesystem($adapter);
    }

    protected function getRemoteLogMessage(array $entry): string {
        // Omit the date, which is not mocked (i.e. the live date)
        $message = $entry['message'];
        return "remote> {$message}";
    }

    public function getRemotePublicPath(): string {
        $public_path = __DIR__."/tmp/test_server/public_html";
        if (!is_dir($public_path)) {
            mkdir($public_path, 0o777, true);
            file_put_contents("{$public_path}/index.php", 'some index stuff');
        }
        return 'public_html';
    }

    public function getRemotePublicUrl(): string {
        return "http://127.0.0.1:{$this->port}";
    }

    public function getRemotePrivatePath(): string {
        $private_deploy_path = __DIR__."/tmp/test_server/private_files/deploy";
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0o777, true);
        }
        return 'private_files';
    }

    /** @return array<string, string> */
    public function install(string $public_path): array {
        // unused, see ./tests/IntegrationTests/resources/*Deploy.php
        return [];
    }

    /** @param array<string, string> $result */
    protected function afterDeploy(array $result): void {
        $json_result = json_encode($result);
        $this->logger?->info("afterDeploy {$json_result}");
    }
}

/**
 * @internal
 *
 * @covers \PhpDeploy\AbstractDeploy
 */
final class AbstractDeployIntegrationTest extends IntegrationTestCase {
    public function testBuild(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();
        $fake_logger = new FakeLogger();
        $fake_deployment_builder->setLogger($fake_logger);

        $fake_deployment_builder->build();

        $local_folder_path = $fake_deployment_builder->getLocalBuildFolderPath();
        $local_zip_path = $fake_deployment_builder->getLocalZipPath();

        $this->assertMatchesRegularExpression('/\/tmp\/local\/local_tmp\/[\S]{24}\/$/', $local_folder_path);
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

        $this->assertSame([
            ['info', 'Build...', []],
            ['info', 'Populate build folder...', []],
            ['info', 'Zip build folder...', []],
            ['info', 'Zipping build folder...', []],
            ['info', 'Zipping done.', []],
            ['info', 'Build done.', []],
        ], $fake_logger->messages);
    }

    public function testBuildAndDeploy(): void {
        $deploy_path = __DIR__.'/tmp/test_server/private_files/deploy/';
        mkdir($deploy_path, 0o777, true);
        mkdir("{$deploy_path}/previous/subdir", 0o777, true);
        file_put_contents(
            "{$deploy_path}/previous/test.txt",
            'build_and_deploy_previous_test'
        );
        file_put_contents(
            "{$deploy_path}/previous/subdir/subtest.txt",
            'build_and_deploy_previous_subtest'
        );
        mkdir("{$deploy_path}/live/subdir", 0o777, true);
        file_put_contents(
            "{$deploy_path}/live/test.txt",
            'build_and_deploy_live_test'
        );
        file_put_contents(
            "{$deploy_path}/live/subdir/subtest.txt",
            'build_and_deploy_live_subtest'
        );

        $public_path = __DIR__.'/tmp/test_server/public_html/';
        mkdir($public_path, 0o777, true);
        $this->startTestServer('127.0.0.1', 8081, $public_path);

        $fake_deployment_builder = new FakeIntegrationDeploy();
        $fake_deployment_builder->port = 8081;
        $fake_logger = new FakeLogger();
        $fake_deployment_builder->setLogger($fake_logger);

        $fake_deployment_builder->buildAndDeploy();

        $remote_base_path = __DIR__.'/tmp/test_server/';
        $remote_zip_path = $fake_deployment_builder->getRemoteZipPath();
        $remote_script_path = $fake_deployment_builder->getRemoteScriptPath();
        $remote_deploy_path = $fake_deployment_builder->getRemoteDeployPath();
        $remote_public_path = $fake_deployment_builder->getRemotePublicPath();

        $this->assertSame(false, is_file("{$remote_base_path}/{$remote_zip_path}"));
        $this->assertSame(false, is_file("{$remote_base_path}/{$remote_script_path}"));
        $this->assertSame(true, is_dir("{$remote_base_path}/{$remote_deploy_path}"));
        $this->assertSame(false, is_dir("{$remote_base_path}/{$remote_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$remote_base_path}/{$remote_deploy_path}/previous"));
        $this->assertSame(
            'build_and_deploy_live_test',
            file_get_contents("{$remote_base_path}/{$remote_deploy_path}/previous/test.txt")
        );
        $this->assertSame(
            'build_and_deploy_live_subtest',
            file_get_contents("{$remote_base_path}/{$remote_deploy_path}/previous/subdir/subtest.txt")
        );
        $this->assertSame(true, is_dir("{$remote_base_path}/{$remote_deploy_path}/live"));
        $this->assertSame(
            'test1234',
            file_get_contents("{$remote_base_path}/{$remote_deploy_path}/live/test.txt")
        );
        $this->assertSame(
            'subtest1234',
            file_get_contents("{$remote_base_path}/{$remote_deploy_path}/live/subdir/subtest.txt")
        );
        $this->assertSame(
            'test1234',
            file_get_contents("{$remote_base_path}/{$remote_public_path}/index.txt")
        );

        $this->assertSame([
            ['info', 'Build...', []],
            ['info', 'Populate build folder...', []],
            ['info', 'Zip build folder...', []],
            ['info', 'Zipping build folder...', []],
            ['info', 'Zipping done.', []],
            ['info', 'Build done.', []],
            ['info', 'Deploy...', []],
            ['info', 'Upload (6.88 MB)...', []],
            ['info', 'Upload done.', []],
            ['info', 'Running deploy script (http://127.0.0.1:8081/***/deploy.php)...', []],
            ['info', 'remote> Initialize...', []],
            ['info', 'remote> Run some checks...', []],
            ['info', 'remote> Unzip the uploaded file to candidate directory...', []],
            ['info', 'remote> Remove the zip file...', []],
            ['info', 'remote> Put the candidate live...', []],
            ['info', 'remote> Clean up...', []],
            ['info', 'remote> Install...', []],
            ['info', 'remote> From install method: Copying stuff...', []],
            ['info', 'remote> Copied args? 1', []],
            ['info', 'remote> Done.', []],
            ['info', 'Deploy done with result: {"file":"Deploy.php","result":"dependent-deploy-result"}', []],
            ['info', 'afterDeploy {"file":"Deploy.php","result":"dependent-deploy-result"}', []],
        ], array_map(function ($entry) {
            return [$entry[0], preg_replace('/\/[\S]{24}\//', '/***/', strval($entry[1])), $entry[2]];
        }, $fake_logger->messages));
    }

    public function testFreshDeploy(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();
        $fake_logger = new FakeLogger();
        $fake_deployment_builder->setLogger($fake_logger);

        $local_zip_path = $fake_deployment_builder->getLocalZipPath();
        $zip = new \ZipArchive();
        $zip->open($local_zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile(__DIR__.'/resources/StandaloneDeploy.php', 'Deploy.php');
        $zip->addFromString('test.txt', 'test1234');
        $zip->addFromString('subdir/subtest.txt', 'subtest1234');
        $zip->close();

        $public_path = __DIR__.'/tmp/test_server/public_html/';
        mkdir($public_path, 0o777, true);
        $this->startTestServer('127.0.0.1', 8082, $public_path);
        $fake_deployment_builder->port = 8082;

        $result = $fake_deployment_builder->deploy();

        $remote_base_path = __DIR__.'/tmp/test_server/';
        $remote_zip_path = $fake_deployment_builder->getRemoteZipPath();
        $remote_script_path = $fake_deployment_builder->getRemoteScriptPath();
        $remote_deploy_path = $fake_deployment_builder->getRemoteDeployPath();
        $remote_public_path = $fake_deployment_builder->getRemotePublicPath();

        $this->assertSame(false, is_file("{$remote_base_path}/{$remote_zip_path}"));
        $this->assertSame(false, is_file("{$remote_base_path}/{$remote_script_path}"));
        $this->assertSame(true, is_dir("{$remote_base_path}/{$remote_deploy_path}"));
        $this->assertSame(false, is_dir("{$remote_base_path}/{$remote_deploy_path}/candidate"));
        $this->assertSame(false, is_dir("{$remote_base_path}/{$remote_deploy_path}/previous"));
        $this->assertSame(true, is_dir("{$remote_base_path}/{$remote_deploy_path}/live"));
        $this->assertSame(
            'test1234',
            file_get_contents("{$remote_base_path}/{$remote_deploy_path}/live/test.txt")
        );
        $this->assertSame(
            'subtest1234',
            file_get_contents("{$remote_base_path}/{$remote_deploy_path}/live/subdir/subtest.txt")
        );
        $this->assertSame(
            'test1234',
            file_get_contents("{$remote_base_path}/{$remote_public_path}/index.txt")
        );
        $this->assertSame(
            'args_copied_correctly=1',
            file_get_contents("{$remote_base_path}/{$remote_public_path}/index.log")
        );

        $this->assertSame([
            ['info', 'Deploy...', []],
            ['info', 'Upload (729 bytes)...', []],
            ['info', 'Upload done.', []],
            ['info', 'Running deploy script (http://127.0.0.1:8082/***/deploy.php)...', []],
            ['info', 'remote> Initialize...', []],
            ['info', 'remote> Run some checks...', []],
            ['info', 'remote> Unzip the uploaded file to candidate directory...', []],
            ['info', 'remote> Remove the zip file...', []],
            ['info', 'remote> Put the candidate live...', []],
            ['info', 'remote> Clean up...', []],
            ['info', 'remote> Install...', []],
            ['info', 'remote> Done.', []],
            ['info', 'Deploy done with result: {"file":"Deploy.php","result":"standalone-deploy-result"}', []],
        ], array_map(function ($entry) {
            return [$entry[0], preg_replace('/\/[\S]{24}\//', '/***/', strval($entry[1])), $entry[2]];
        }, $fake_logger->messages));

        $this->assertSame([
            'file' => 'Deploy.php',
            'result' => 'standalone-deploy-result',
        ], $result);
    }

    public function testDeployNoPrevious(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();
        $fake_logger = new FakeLogger();
        $fake_deployment_builder->setLogger($fake_logger);

        $local_zip_path = $fake_deployment_builder->getLocalZipPath();
        $zip = new \ZipArchive();
        $zip->open($local_zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile(__DIR__.'/resources/StandaloneDeploy.php', 'Deploy.php');
        $zip->addFromString('test.txt', 'test1234');
        $zip->addFromString('subdir/subtest.txt', 'subtest1234');
        $zip->close();

        $deploy_path = __DIR__.'/tmp/test_server/private_files/deploy/';
        mkdir("{$deploy_path}/live/subdir", 0o777, true);
        file_put_contents(
            "{$deploy_path}/live/test.txt",
            'build_and_deploy_live_test'
        );
        file_put_contents(
            "{$deploy_path}/live/subdir/subtest.txt",
            'build_and_deploy_live_subtest'
        );

        $public_path = __DIR__.'/tmp/test_server/public_html/';
        mkdir($public_path, 0o777, true);
        $this->startTestServer('127.0.0.1', 8083, $public_path);
        $fake_deployment_builder->port = 8083;

        $result = $fake_deployment_builder->deploy();

        $remote_base_path = __DIR__.'/tmp/test_server/';
        $remote_zip_path = $fake_deployment_builder->getRemoteZipPath();
        $remote_script_path = $fake_deployment_builder->getRemoteScriptPath();
        $remote_deploy_path = $fake_deployment_builder->getRemoteDeployPath();
        $remote_public_path = $fake_deployment_builder->getRemotePublicPath();

        $this->assertSame(false, is_file("{$remote_base_path}/{$remote_zip_path}"));
        $this->assertSame(false, is_file("{$remote_base_path}/{$remote_script_path}"));
        $this->assertSame(true, is_dir("{$remote_base_path}/{$remote_deploy_path}"));
        $this->assertSame(false, is_dir("{$remote_base_path}/{$remote_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$remote_base_path}/{$remote_deploy_path}/previous"));
        $this->assertSame(
            'build_and_deploy_live_test',
            file_get_contents("{$remote_base_path}/{$remote_deploy_path}/previous/test.txt")
        );
        $this->assertSame(
            'build_and_deploy_live_subtest',
            file_get_contents("{$remote_base_path}/{$remote_deploy_path}/previous/subdir/subtest.txt")
        );
        $this->assertSame(true, is_dir("{$remote_base_path}/{$remote_deploy_path}/live"));
        $this->assertSame(
            'test1234',
            file_get_contents("{$remote_base_path}/{$remote_deploy_path}/live/test.txt")
        );
        $this->assertSame(
            'subtest1234',
            file_get_contents("{$remote_base_path}/{$remote_deploy_path}/live/subdir/subtest.txt")
        );
        $this->assertSame(
            'test1234',
            file_get_contents("{$remote_base_path}/{$remote_public_path}/index.txt")
        );

        $this->assertSame([
            ['info', 'Deploy...', []],
            ['info', 'Upload (729 bytes)...', []],
            ['info', 'Upload done.', []],
            ['info', 'Running deploy script (http://127.0.0.1:8083/***/deploy.php)...', []],
            ['info', 'remote> Initialize...', []],
            ['info', 'remote> Run some checks...', []],
            ['info', 'remote> Unzip the uploaded file to candidate directory...', []],
            ['info', 'remote> Remove the zip file...', []],
            ['info', 'remote> Put the candidate live...', []],
            ['info', 'remote> Clean up...', []],
            ['info', 'remote> Install...', []],
            ['info', 'remote> Done.', []],
            ['info', 'Deploy done with result: {"file":"Deploy.php","result":"standalone-deploy-result"}', []],
        ], array_map(function ($entry) {
            return [$entry[0], preg_replace('/\/[\S]{24}\//', '/***/', strval($entry[1])), $entry[2]];
        }, $fake_logger->messages));

        $this->assertSame([
            'file' => 'Deploy.php',
            'result' => 'standalone-deploy-result',
        ], $result);
    }

    public function testGetLocalBuildFolderPath(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getLocalBuildFolderPath();
        $this->assertMatchesRegularExpression('/\/tmp\/local\/local_tmp\/[\S]{24}\/$/', $result);
    }

    public function testGetLocalZipPath(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getLocalZipPath();
        $this->assertMatchesRegularExpression('/\/tmp\/local\/local_tmp\/[\S]{24}\.zip$/', $result);
    }

    public function testGetRemoteDeployPath(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getRemoteDeployPath();
        $this->assertSame('private_files/deploy', $result);
    }

    public function testGetRemoteDeployDirname(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getRemoteDeployDirname();
        $this->assertSame('deploy', $result);
    }

    public function testGetRemoteZipPath(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getRemoteZipPath();
        $this->assertMatchesRegularExpression('/public_html\/[\S]{24}\/deploy\.zip$/', $result);
    }

    public function testGetRemoteScriptPath(): void {
        $fake_deployment_builder = new FakeIntegrationDeploy();

        $result = $fake_deployment_builder->getRemoteScriptPath();
        $this->assertMatchesRegularExpression('/public_html\/[\S]{24}\/deploy\.php$/', $result);
    }
}
