<?php

declare(strict_types=1);

use PhpDeploy\RemoteDeployBootstrap;

require_once __DIR__.'/_common/UnitTestCase.php';

class FakeRemoteDeployBootstrap extends RemoteDeployBootstrap {
    public $public_path = 'public_html';

    protected function getDateString() {
        return '2020-03-16_09_00_00';
    }

    protected function getDeployPath() {
        return 'private_files/deploy';
    }

    protected function getPublicPath() {
        return $this->public_path;
    }

    protected function getPublicDeployPath() {
        return __DIR__.'/tmp/public_html/ABCDEFGHIJ';
    }

    public function testOnlyRemoveR($path) {
        return parent::remove_r($path);
    }

    public function testOnlyGetDateString() {
        return parent::getDateString();
    }

    public function testOnlyGetDeployPath() {
        return parent::getDeployPath();
    }

    public function testOnlyGetPublicPath() {
        return parent::getPublicPath();
    }

    public function testOnlyGetPublicDeployPath() {
        return parent::getPublicDeployPath();
    }

    public function testOnlyGetOverrideOrDefault($override, $default) {
        return parent::getOverrideOrDefault($override, $default);
    }
}

/**
 * @internal
 * @covers \PhpDeploy\RemoteDeployBootstrap
 */
final class RemoteDeployBootstrapTest extends UnitTestCase {
    public function testRun(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0777, true);
        }
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0777, true);
        }
        $zip = new \ZipArchive();
        $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('test.txt', 'test1234');
        $zip->addFromString('subdir/subtest.txt', 'subtest1234');
        $zip->close();
        file_put_contents($php_path, 'whatever');

        $this->assertSame(true, is_file($zip_path));
        $this->assertSame(true, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));

        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        $fake_remote_deploy_bootstrap->run();

        $this->assertSame(false, is_file($zip_path));
        $this->assertSame(false, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));
    }

    public function testRunInexistentPublicPath(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        $fake_remote_deploy_bootstrap->public_path = 'inexistent';

        try {
            $fake_remote_deploy_bootstrap->run();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertMatchesRegularExpression(
                '/^Did not find the public path \\(inexistent\\) in .*\\/tmp\\/public_html\\/ABCDEFGHIJ$/',
                $th->getMessage()
            );
            // throw $th;
        }
    }

    public function testRunInexistentPrivateDirectory(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0777, true);
        }
        $zip = new \ZipArchive();
        $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('test.txt', 'test1234');
        $zip->addFromString('subdir/subtest.txt', 'subtest1234');
        $zip->close();
        file_put_contents($php_path, 'whatever');

        $this->assertSame(true, is_file($zip_path));
        $this->assertSame(true, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));
        $this->assertSame(false, is_file("{$public_deploy_path}/invalid_deploy_2020-03-16_09_00_00.zip"));

        try {
            $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
            $fake_remote_deploy_bootstrap->run();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertMatchesRegularExpression(
                '/Deploy path \\(.*\\/tmp\\/private_files\\/deploy\\) does not exist/',
                $th->getMessage()
            );
        }
        $this->assertSame(false, is_file($zip_path));
        $this->assertSame(false, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));
        $this->assertSame(true, is_file("{$public_deploy_path}/invalid_deploy_2020-03-16_09_00_00.zip"));
    }

    public function testRunWithInvalidZip(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0777, true);
        }
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0777, true);
        }
        file_put_contents($zip_path, 'whatever');
        file_put_contents($php_path, 'whatever');

        $this->assertSame(true, is_file($zip_path));
        $this->assertSame(true, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/invalid_candidate_2020-03-16_09_00_00"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));
        $this->assertSame(false, is_file("{$public_deploy_path}/invalid_deploy_2020-03-16_09_00_00.zip"));

        try {
            $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
            $fake_remote_deploy_bootstrap->run();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $correct_message = (
                // PHP 8
                $th->getMessage() === 'Invalid or uninitialized Zip object'
                // PHP 7
                || $th->getMessage() === 'ZipArchive::extractTo(): Invalid or uninitialized Zip object'
            );
            $this->assertSame(true, $correct_message);
        }
        $this->assertSame(false, is_file($zip_path));
        $this->assertSame(false, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/invalid_candidate_2020-03-16_09_00_00"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));
        $this->assertSame(true, is_file("{$public_deploy_path}/invalid_deploy_2020-03-16_09_00_00.zip"));
    }

    public function testRunWithResidualCandidate(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0777, true);
        }
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0777, true);
        }
        $zip = new \ZipArchive();
        $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('test.txt', 'test1234');
        $zip->addFromString('subdir/subtest.txt', 'subtest1234');
        $zip->close();
        file_put_contents($php_path, 'whatever');
        mkdir("{$private_deploy_path}/candidate");

        $this->assertSame(true, is_file($zip_path));
        $this->assertSame(true, is_file($php_path));
        $this->assertSame(true, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/residual_candidate_2020-03-16_09_00_00"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));

        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        $fake_remote_deploy_bootstrap->run();

        $this->assertSame(false, is_file($zip_path));
        $this->assertSame(false, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/residual_candidate_2020-03-16_09_00_00"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));
    }

    public function testRunWithPreviousDeployments(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0777, true);
        }
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0777, true);
        }
        $zip = new \ZipArchive();
        $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('test.txt', 'test1234');
        $zip->addFromString('subdir/subtest.txt', 'subtest1234');
        $zip->close();
        file_put_contents($php_path, 'whatever');
        mkdir("{$private_deploy_path}/live/subdir", 0777, true);
        file_put_contents(
            "{$private_deploy_path}/live/test.txt",
            'unit_test_live'
        );
        file_put_contents(
            "{$private_deploy_path}/live/subdir/subtest.txt",
            'unit_subtest_live'
        );
        mkdir("{$private_deploy_path}/previous/subdir", 0777, true);
        file_put_contents(
            "{$private_deploy_path}/previous/test.txt",
            'unit_test_previous'
        );
        file_put_contents(
            "{$private_deploy_path}/previous/subdir/subtest.txt",
            'unit_subtest_previous'
        );

        $this->assertSame(true, is_file($zip_path));
        $this->assertSame(true, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/previous"));

        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        $fake_remote_deploy_bootstrap->run();

        $this->assertSame(false, is_file($zip_path));
        $this->assertSame(false, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(
            'test1234',
            file_get_contents("{$private_deploy_path}/live/test.txt")
        );
        $this->assertSame(
            'subtest1234',
            file_get_contents("{$private_deploy_path}/live/subdir/subtest.txt")
        );
        $this->assertSame(true, is_dir("{$private_deploy_path}/previous"));
        $this->assertSame(
            'unit_test_live',
            file_get_contents("{$private_deploy_path}/previous/test.txt")
        );
        $this->assertSame(
            'unit_subtest_live',
            file_get_contents("{$private_deploy_path}/previous/subdir/subtest.txt")
        );
    }

    public function testRemoveR(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        mkdir(__DIR__.'/tmp/dir/subdir', 0777, true);
        file_put_contents(__DIR__.'/tmp/dir/test.txt', 'test1234');
        file_put_contents(__DIR__.'/tmp/dir/subdir/subtest.txt', 'subtest1234');
        symlink(
            __DIR__.'/tmp/dir/subdir/subtest.txt',
            __DIR__.'/tmp/dir/link_to_subtest.txt'
        );

        $this->assertSame(true, is_dir(__DIR__.'/tmp'));
        $this->assertSame(true, is_dir(__DIR__.'/tmp/dir'));
        $this->assertSame(true, is_file(__DIR__.'/tmp/dir/test.txt'));
        $this->assertSame(true, is_link(__DIR__.'/tmp/dir/link_to_subtest.txt'));
        $this->assertSame(true, is_dir(__DIR__.'/tmp/dir/subdir'));
        $this->assertSame(true, is_file(__DIR__.'/tmp/dir/subdir/subtest.txt'));

        $fake_remote_deploy_bootstrap->testOnlyRemoveR(__DIR__.'/tmp/dir');

        $this->assertSame(true, is_dir(__DIR__.'/tmp'));
        $this->assertSame(false, is_dir(__DIR__.'/tmp/dir'));
        $this->assertSame(false, is_file(__DIR__.'/tmp/dir/test.txt'));
        $this->assertSame(false, is_link(__DIR__.'/tmp/dir/link_to_subtest.txt'));
        $this->assertSame(false, is_dir(__DIR__.'/tmp/dir/subdir'));
        $this->assertSame(false, is_file(__DIR__.'/tmp/dir/subdir/subtest.txt'));
    }

    public function testGetDateString(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        $date_string = $fake_remote_deploy_bootstrap->testOnlyGetDateString();

        $this->assertMatchesRegularExpression(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}_[0-9]{2}_[0-9]{2}$/',
            $date_string,
        );
    }

    public function testGetDeployPath(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        $deploy_path = $fake_remote_deploy_bootstrap->testOnlyGetDeployPath();

        $this->assertSame('', $deploy_path);
    }

    public function testGetPublicPath(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        $public_path = $fake_remote_deploy_bootstrap->testOnlyGetPublicPath();

        $this->assertSame('', $public_path);
    }

    public function testGetPublicDeployPath(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        $public_deploy_path = $fake_remote_deploy_bootstrap->testOnlyGetPublicDeployPath();

        $this->assertMatchesRegularExpression('/\\/lib$/', $public_deploy_path);
    }

    public function testGetOverrideOrDefaultReturnOverride(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        $result = $fake_remote_deploy_bootstrap->testOnlyGetOverrideOrDefault('override', 'default');

        $this->assertSame('override', $result);
    }

    public function testGetOverrideOrDefaultReturnDefault(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap();
        $result = $fake_remote_deploy_bootstrap->testOnlyGetOverrideOrDefault('%%%OVERRIDE%%%', 'default');

        $this->assertSame('default', $result);
    }
}
