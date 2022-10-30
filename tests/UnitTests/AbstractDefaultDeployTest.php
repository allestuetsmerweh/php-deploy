<?php

declare(strict_types=1);

namespace PhpDeploy\Tests\UnitTests;

use PhpDeploy\AbstractDefaultDeploy;
use PhpDeploy\Tests\UnitTests\Common\UnitTestCase;

class FakeDefaultDeploy extends AbstractDefaultDeploy {
    use \Psr\Log\LoggerAwareTrait;

    public $command_line_options = [];
    public $environment_variables = [];

    public $build_and_deploy_called = false;
    public $installed_to;

    protected function populateFolder() {
    }

    protected function getFlysystemFilesystem() {
        return null;
    }

    public function getRemotePublicPath() {
        return null;
    }

    public function getRemotePublicUrl() {
        return null;
    }

    public function getRemotePrivatePath() {
        return null;
    }

    public function buildAndDeploy() {
        $this->build_and_deploy_called = true;
    }

    protected function getCommandLineOptions() {
        return $this->command_line_options;
    }

    protected function getEnvironmentVariable($variable_name) {
        return $this->environment_variables[$variable_name];
    }

    public function install($public_path) {
        $this->installed_to = $public_path;
    }

    public function testOnlyGetUsername() {
        return $this->username;
    }

    public function testOnlyGetPassword() {
        return $this->password;
    }

    public function testOnlyGetTarget() {
        return $this->target;
    }

    public function testOnlySetTarget($new_target) {
        $this->target = $new_target;
    }

    public function testOnlyGetEnvironment() {
        return $this->environment;
    }

    public function testOnlySetEnvironment($new_environment) {
        $this->environment = $new_environment;
    }

    public function testOnlyGetRemotePublicRandomDeployDirname() {
        return parent::getRemotePublicRandomDeployDirname();
    }

    public function testOnlySetRemotePublicRandomDeployDirname($new) {
        $this->remote_public_random_deploy_dirname = $new;
    }
}

/**
 * @internal
 *
 * @covers \PhpDeploy\AbstractDefaultDeploy
 */
final class AbstractDefaultDeployTest extends UnitTestCase {
    public function testGetArgs(): void {
        $fake_default_deploy = new FakeDefaultDeploy();
        $fake_default_deploy->testOnlySetTarget('host1');
        $fake_default_deploy->testOnlySetEnvironment('unit-test');
        $fake_default_deploy->testOnlySetRemotePublicRandomDeployDirname('just/for/test');
        $this->assertSame([
            'remote_public_random_deploy_dirname' => 'just/for/test',
            'target' => 'host1',
            'environment' => 'unit-test',
        ], $fake_default_deploy->getArgs());
    }

    public function testInjectArgs(): void {
        $fake_default_deploy = new FakeDefaultDeploy();
        $fake_default_deploy->injectArgs([
            'remote_public_random_deploy_dirname' => 'just/for/test',
            'environment' => 'unit-test',
        ]);

        $this->assertSame('just/for/test', $fake_default_deploy->testOnlyGetRemotePublicRandomDeployDirname());
        $this->assertSame('unit-test', $fake_default_deploy->testOnlyGetEnvironment());
    }

    public function testCliWithoutArgv(): void {
        $fake_default_deploy = new FakeDefaultDeploy();
        try {
            $fake_default_deploy->cli();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $correct_message = (
                // PHP 8
                $th->getMessage() === 'Undefined array key "target"'
                // PHP 7
                || $th->getMessage() === 'Undefined index: target'
            );
            $this->assertSame(true, $correct_message);
            $this->assertSame(null, $fake_default_deploy->testOnlyGetEnvironment());
            $this->assertSame(null, $fake_default_deploy->testOnlyGetUsername());
            $this->assertSame(null, $fake_default_deploy->testOnlyGetPassword());
        }
    }

    public function testCli(): void {
        $fake_default_deploy = new FakeDefaultDeploy();
        $fake_default_deploy->command_line_options = [
            'target' => 'host1',
            'environment' => 'prod',
            'username' => 'user@host.tld',
        ];
        $fake_default_deploy->environment_variables = [
            'PASSWORD' => 'secret',
        ];

        $fake_default_deploy->cli();

        $this->assertSame(true, $fake_default_deploy->build_and_deploy_called);
        $this->assertSame('host1', $fake_default_deploy->testOnlyGetTarget());
        $this->assertSame('prod', $fake_default_deploy->testOnlyGetEnvironment());
        $this->assertSame('user@host.tld', $fake_default_deploy->testOnlyGetUsername());
        $this->assertSame('secret', $fake_default_deploy->testOnlyGetPassword());
    }
}
