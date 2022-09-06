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
}

/**
 * @internal
 *
 * @covers \PhpDeploy\AbstractDefaultDeploy
 */
final class AbstractDefaultDeployTest extends UnitTestCase {
    public function testCliWithoutArgv(): void {
        $fake_default_deploy = new FakeDefaultDeploy();
        try {
            $fake_deployment_builder = $fake_default_deploy->cli();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $correct_message = (
                // PHP 8
                $th->getMessage() === 'Undefined array key "environment"'
                // PHP 7
                || $th->getMessage() === 'Undefined index: environment'
            );
            $this->assertSame(true, $correct_message);
        }
    }

    public function testCli(): void {
        $fake_default_deploy = new FakeDefaultDeploy();
        $fake_default_deploy->command_line_options = [
            'environment' => 'prod',
            'username' => 'user@host.tld',
        ];
        $fake_default_deploy->environment_variables = [
            'PASSWORD' => 'secret',
        ];

        $fake_default_deploy->cli();

        $this->assertSame(true, $fake_default_deploy->build_and_deploy_called);
    }
}
