<?php

declare(strict_types=1);

use PhpDeploy\AbstractDefaultDeploy;

require_once __DIR__.'/_common/UnitTestCase.php';

class FakeDefaultDeploy extends AbstractDefaultDeploy {
    public $command_line_options = [];
    public $environment_variables = [];

    public $build_and_deploy_called = false;

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
}

/**
 * @internal
 * @covers \PhpDeploy\AbstractDefaultDeploy
 */
final class AbstractDefaultDeployTest extends UnitTestCase {
    public function testCliWithoutArgv(): void {
        $fake_default_deploy = new FakeDefaultDeploy();
        try {
            $fake_deployment_builder = $fake_default_deploy->cli();
            throw new Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertSame(2, $th->getCode());
            $this->assertSame('Undefined array key "environment"', $th->getMessage());
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
