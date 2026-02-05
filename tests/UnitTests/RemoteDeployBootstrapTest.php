<?php

declare(strict_types=1);

namespace PhpDeploy\Tests\UnitTests;

use PhpDeploy\RemoteDeployBootstrap;
use PhpDeploy\RemoteDeployLogger;
use PhpDeploy\Tests\UnitTests\Common\UnitTestCase;

class FakeRemoteDeployBootstrap extends RemoteDeployBootstrap {
    public string $public_path = 'public_html';

    protected function getDateString(): string {
        return '2020-03-16_09_00_00';
    }

    protected function getDeployPath(): string {
        return 'private_files/deploy';
    }

    protected function getPublicPath(): string {
        return $this->public_path;
    }

    protected function getPublicDeployPath(): string {
        return __DIR__.'/tmp/public_html/ABCDEFGHIJ';
    }

    public function testOnlyRemoveRecursive(string $path): void {
        parent::removeRecursive($path);
    }

    public function testOnlyGetDateString(): string {
        return parent::getDateString();
    }

    public function testOnlyGetDeployPath(): string {
        return parent::getDeployPath();
    }

    public function testOnlyGetPublicPath(): string {
        return parent::getPublicPath();
    }

    /** @return array<string, string> */
    public function testOnlyGetArgs(): array {
        return parent::getArgs();
    }

    public function testOnlyGetPublicDeployPath(): string {
        return parent::getPublicDeployPath();
    }

    public function testOnlyGetOverrideOrDefault(string $override, string $default): string {
        return parent::getOverrideOrDefault($override, $default);
    }
}

class FakeRemoteDeployBootstrapWithOverrides extends RemoteDeployBootstrap {
    public string $DEPLOY_PATH_OVERRIDE = 'private_files/deploy/override';
    public string $PUBLIC_PATH_OVERRIDE = 'public_html/override';
    public string $ARGS_OVERRIDE = '{"just":"test"}';

    public function testOnlyGetDeployPath(): string {
        return parent::getDeployPath();
    }

    public function testOnlyGetPublicPath(): string {
        return parent::getPublicPath();
    }

    /** @return array<string, string> */
    public function testOnlyGetArgs(): array {
        return parent::getArgs();
    }

    public function testOnlyGetPublicDeployPath(): string {
        return parent::getPublicDeployPath();
    }

    public function testOnlyGetOverrideOrDefault(string $override, string $default): string {
        return parent::getOverrideOrDefault($override, $default);
    }
}

/**
 * @internal
 *
 * @covers \PhpDeploy\RemoteDeployBootstrap
 */
final class RemoteDeployBootstrapTest extends UnitTestCase {
    public function testRun(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0o777, true);
        }
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0o777, true);
        }
        $zip = new \ZipArchive();
        $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile(__DIR__.'/resources/Deploy.php', 'Deploy.php');
        $zip->addFromString('test.txt', 'test1234');
        $zip->addFromString('subdir/subtest.txt', 'subtest1234');
        $zip->close();
        file_put_contents($php_path, 'whatever');

        $this->assertSame(true, is_file($zip_path));
        $this->assertSame(true, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));

        $fake_logger = new RemoteDeployLogger();
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap($fake_logger);
        $fake_remote_deploy_bootstrap->run();

        $this->assertSame(false, is_file($zip_path));
        $this->assertSame(false, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));

        $this->assertMatchesRegularExpression(
            '/\/tmp\/public_html$/',
            strval(file_get_contents("{$private_deploy_path}/live/installed_to.txt"))
        );

        $this->assertSame([
            ['info', 'Initialize...', []],
            ['info', 'Run some checks...', []],
            ['info', 'Unzip the uploaded file to candidate directory...', []],
            ['info', 'Remove the zip file...', []],
            ['info', 'Put the candidate live...', []],
            ['info', 'Clean up...', []],
            ['info', 'Install...', []],
            ['info', 'Logger injected', []],
            ['info', 'Args injected: []', []],
            ['info', 'Done.', []],
        ], $this->convertLogs($fake_logger->messages));
    }

    public function testRunInexistentPublicPath(): void {
        $fake_logger = new RemoteDeployLogger();
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap($fake_logger);
        $fake_remote_deploy_bootstrap->public_path = 'inexistent';

        try {
            $fake_remote_deploy_bootstrap->run();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertMatchesRegularExpression(
                '/^Did not find the public path \(inexistent\) in .*\/tmp\/public_html\/ABCDEFGHIJ$/',
                $th->getMessage()
            );
        }

        $this->assertSame([
            ['info', 'Initialize...', []],
            ['info', 'Clean up...', []],
        ], $this->convertLogs($fake_logger->messages));
    }

    public function testRunInexistentPrivateDirectory(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0o777, true);
        }
        $zip = new \ZipArchive();
        $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile(__DIR__.'/resources/Deploy.php', 'Deploy.php');
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
            $fake_logger = new RemoteDeployLogger();
            $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap($fake_logger);
            $fake_remote_deploy_bootstrap->run();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertMatchesRegularExpression(
                '/Deploy path \(.*\/tmp\/private_files\/deploy\) does not exist/',
                $th->getMessage()
            );
        }
        $this->assertSame(false, is_file($zip_path));
        $this->assertSame(false, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));
        $this->assertSame(true, is_file("{$public_deploy_path}/invalid_deploy_2020-03-16_09_00_00.zip"));

        $this->assertSame([
            ['info', 'Initialize...', []],
            ['info', 'Run some checks...', []],
            ['info', 'Clean up...', []],
        ], $this->convertLogs($fake_logger->messages));
    }

    public function testRunWithInvalidZip(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0o777, true);
        }
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0o777, true);
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
            $fake_logger = new RemoteDeployLogger();
            $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap($fake_logger);
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

        $this->assertSame([
            ['info', 'Initialize...', []],
            ['info', 'Run some checks...', []],
            ['info', 'Unzip the uploaded file to candidate directory...', []],
            ['info', 'Clean up...', []],
        ], $this->convertLogs($fake_logger->messages));
    }

    public function testRunWithResidualCandidate(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0o777, true);
        }
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0o777, true);
        }
        $zip = new \ZipArchive();
        $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile(__DIR__.'/resources/Deploy.php', 'Deploy.php');
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

        $fake_logger = new RemoteDeployLogger();
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap($fake_logger);
        $fake_remote_deploy_bootstrap->run();

        $this->assertSame(false, is_file($zip_path));
        $this->assertSame(false, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/residual_candidate_2020-03-16_09_00_00"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));

        $this->assertMatchesRegularExpression(
            '/\/tmp\/public_html$/',
            strval(file_get_contents("{$private_deploy_path}/live/installed_to.txt"))
        );

        $this->assertSame([
            ['info', 'Initialize...', []],
            ['info', 'Run some checks...', []],
            ['info', 'A previous deployment failed. Save residual candidate...', []],
            ['info', 'Unzip the uploaded file to candidate directory...', []],
            ['info', 'Remove the zip file...', []],
            ['info', 'Put the candidate live...', []],
            ['info', 'Clean up...', []],
            ['info', 'Install...', []],
            ['info', 'Logger injected', []],
            ['info', 'Args injected: []', []],
            ['info', 'Done.', []],
        ], $this->convertLogs($fake_logger->messages));
    }

    public function testRunWithPreviousDeployments(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0o777, true);
        }
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0o777, true);
        }
        $zip = new \ZipArchive();
        $zip->open($zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile(__DIR__.'/resources/Deploy.php', 'Deploy.php');
        $zip->addFromString('test.txt', 'test1234');
        $zip->addFromString('subdir/subtest.txt', 'subtest1234');
        $zip->close();
        file_put_contents($php_path, 'whatever');
        mkdir("{$private_deploy_path}/live/subdir", 0o777, true);
        file_put_contents(
            "{$private_deploy_path}/live/test.txt",
            'unit_test_live'
        );
        file_put_contents(
            "{$private_deploy_path}/live/subdir/subtest.txt",
            'unit_subtest_live'
        );
        mkdir("{$private_deploy_path}/previous/subdir", 0o777, true);
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

        $fake_logger = new RemoteDeployLogger();
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap($fake_logger);
        $fake_remote_deploy_bootstrap->run();

        $this->assertSame(false, is_file($zip_path));
        $this->assertSame(false, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(
            'test1234',
            strval(file_get_contents("{$private_deploy_path}/live/test.txt"))
        );
        $this->assertSame(
            'subtest1234',
            strval(file_get_contents("{$private_deploy_path}/live/subdir/subtest.txt"))
        );
        $this->assertSame(true, is_dir("{$private_deploy_path}/previous"));
        $this->assertSame(
            'unit_test_live',
            strval(file_get_contents("{$private_deploy_path}/previous/test.txt"))
        );
        $this->assertSame(
            'unit_subtest_live',
            strval(file_get_contents("{$private_deploy_path}/previous/subdir/subtest.txt"))
        );

        $this->assertMatchesRegularExpression(
            '/\/tmp\/public_html$/',
            strval(file_get_contents("{$private_deploy_path}/live/installed_to.txt"))
        );

        $this->assertSame([
            ['info', 'Initialize...', []],
            ['info', 'Run some checks...', []],
            ['info', 'Unzip the uploaded file to candidate directory...', []],
            ['info', 'Remove the zip file...', []],
            ['info', 'Put the candidate live...', []],
            ['info', 'Clean up...', []],
            ['info', 'Install...', []],
            ['info', 'Logger injected', []],
            ['info', 'Args injected: []', []],
            ['info', 'Done.', []],
        ], $this->convertLogs($fake_logger->messages));
    }

    public function testRunWithoutDeployPhp(): void {
        $public_deploy_path = __DIR__.'/tmp/public_html/ABCDEFGHIJ';
        $private_deploy_path = __DIR__.'/tmp/private_files/deploy';
        $zip_path = "{$public_deploy_path}/deploy.zip";
        $php_path = "{$public_deploy_path}/deploy.php";
        if (!is_dir($public_deploy_path)) {
            mkdir($public_deploy_path, 0o777, true);
        }
        if (!is_dir($private_deploy_path)) {
            mkdir($private_deploy_path, 0o777, true);
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

        try {
            $fake_logger = new RemoteDeployLogger();
            $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap($fake_logger);
            $fake_remote_deploy_bootstrap->run();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertSame('Deploy.php not found', $th->getMessage());
        }

        $this->assertSame(false, is_file($zip_path));
        $this->assertSame(false, is_file($php_path));
        $this->assertSame(false, is_dir("{$private_deploy_path}/candidate"));
        $this->assertSame(true, is_dir("{$private_deploy_path}/live"));
        $this->assertSame(false, is_dir("{$private_deploy_path}/previous"));

        $this->assertSame([
            ['info', 'Initialize...', []],
            ['info', 'Run some checks...', []],
            ['info', 'Unzip the uploaded file to candidate directory...', []],
            ['info', 'Remove the zip file...', []],
            ['info', 'Put the candidate live...', []],
            ['info', 'Clean up...', []],
            ['info', 'Install...', []],
            ['info', 'Clean up...', []],
        ], $this->convertLogs($fake_logger->messages));
    }

    public function testRemoveR(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap(new RemoteDeployLogger());
        mkdir(__DIR__.'/tmp/dir/subdir', 0o777, true);
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

        $fake_remote_deploy_bootstrap->testOnlyRemoveRecursive(__DIR__.'/tmp/dir');

        $this->assertSame(true, is_dir(__DIR__.'/tmp'));
        $this->assertSame(false, is_dir(__DIR__.'/tmp/dir'));
        $this->assertSame(false, is_file(__DIR__.'/tmp/dir/test.txt'));
        $this->assertSame(false, is_link(__DIR__.'/tmp/dir/link_to_subtest.txt'));
        $this->assertSame(false, is_dir(__DIR__.'/tmp/dir/subdir'));
        $this->assertSame(false, is_file(__DIR__.'/tmp/dir/subdir/subtest.txt'));
    }

    public function testGetDateString(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap(new RemoteDeployLogger());
        $date_string = $fake_remote_deploy_bootstrap->testOnlyGetDateString();

        $this->assertMatchesRegularExpression(
            '/^[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}_[0-9]{2}_[0-9]{2}$/',
            $date_string,
        );
    }

    public function testGetDeployPath(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap(new RemoteDeployLogger());
        $deploy_path = $fake_remote_deploy_bootstrap->testOnlyGetDeployPath();

        $this->assertSame('', $deploy_path);
    }

    public function testGetPublicPath(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap(new RemoteDeployLogger());
        $public_path = $fake_remote_deploy_bootstrap->testOnlyGetPublicPath();

        $this->assertSame('', $public_path);
    }

    public function testGetArgs(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap(new RemoteDeployLogger());
        $args = $fake_remote_deploy_bootstrap->testOnlyGetArgs();

        $this->assertSame([], $args);
    }

    public function testGetPublicDeployPath(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap(new RemoteDeployLogger());
        $public_deploy_path = $fake_remote_deploy_bootstrap->testOnlyGetPublicDeployPath();

        $this->assertMatchesRegularExpression('/\/lib$/', $public_deploy_path);
    }

    public function testGetOverrideOrDefaultReturnOverride(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap(new RemoteDeployLogger());
        $result = $fake_remote_deploy_bootstrap->testOnlyGetOverrideOrDefault('override', 'default');

        $this->assertSame('override', $result);
    }

    public function testGetOverrideOrDefaultReturnDefault(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrap(new RemoteDeployLogger());
        $result = $fake_remote_deploy_bootstrap->testOnlyGetOverrideOrDefault('%%%OVERRIDE%%%', 'default');

        $this->assertSame('default', $result);
    }

    public function testGetDeployPathWithOverrides(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrapWithOverrides(new RemoteDeployLogger());
        $deploy_path = $fake_remote_deploy_bootstrap->testOnlyGetDeployPath();

        $this->assertSame('private_files/deploy/override', $deploy_path);
    }

    public function testGetPublicPathWithOverrides(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrapWithOverrides(new RemoteDeployLogger());
        $public_path = $fake_remote_deploy_bootstrap->testOnlyGetPublicPath();

        $this->assertSame('public_html/override', $public_path);
    }

    public function testGetArgsWithOverrides(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrapWithOverrides(new RemoteDeployLogger());
        $args = $fake_remote_deploy_bootstrap->testOnlyGetArgs();

        $this->assertSame(['just' => 'test'], $args);
    }

    public function testGetPublicDeployPathWithOverrides(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrapWithOverrides(new RemoteDeployLogger());
        $public_deploy_path = $fake_remote_deploy_bootstrap->testOnlyGetPublicDeployPath();

        $this->assertMatchesRegularExpression('/\/lib$/', $public_deploy_path);
    }

    public function testGetOverrideOrDefaultReturnOverrideWithOverrides(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrapWithOverrides(new RemoteDeployLogger());
        $result = $fake_remote_deploy_bootstrap->testOnlyGetOverrideOrDefault('override', 'default');

        $this->assertSame('override', $result);
    }

    public function testGetOverrideOrDefaultReturnDefaultWithOverrides(): void {
        $fake_remote_deploy_bootstrap = new FakeRemoteDeployBootstrapWithOverrides(new RemoteDeployLogger());
        $result = $fake_remote_deploy_bootstrap->testOnlyGetOverrideOrDefault('%%%OVERRIDE%%%', 'default');

        $this->assertSame('default', $result);
    }

    /**
     * @param array<array{level: string, timestamp: float, message: string|\Stringable, context: array<mixed>}> $messages
     *
     * @return array<array{0: mixed, 1: string|\Stringable, 2: array<mixed>}>
     */
    private function convertLogs(array $messages): array {
        return array_map(function ($entry) {
            return [$entry['level'], $entry['message'], $entry['context']];
        }, $messages);
    }
}
