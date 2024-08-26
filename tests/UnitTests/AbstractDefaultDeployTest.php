<?php

declare(strict_types=1);

namespace PhpDeploy\Tests\UnitTests;

use League\Flysystem\Filesystem;
use PhpDeploy\AbstractDefaultDeploy;
use PhpDeploy\Tests\UnitTests\Common\UnitTestCase;

class FakeDefaultDeploy extends AbstractDefaultDeploy {
    use \Psr\Log\LoggerAwareTrait;

    /** @var array<string, string> */
    public array $command_line_options = [];
    /** @var array<string, string> */
    public array $environment_variables = [];

    public bool $build_and_deploy_called = false;
    public ?string $installed_to;

    protected ?string $remote_public_random_deploy_dirname = null;

    protected function populateFolder(): void {
    }

    protected function getFlysystemFilesystem(): Filesystem {
        throw new \Exception("not implemented");
    }

    public function getRemotePublicPath(): string {
        throw new \Exception("not implemented");
    }

    public function getRemotePublicUrl(): string {
        throw new \Exception("not implemented");
    }

    public function getRemotePrivatePath(): string {
        throw new \Exception("not implemented");
    }

    public function buildAndDeploy(): void {
        $this->build_and_deploy_called = true;
    }

    protected function getCommandLineOptions(): array {
        return $this->command_line_options;
    }

    protected function getEnvironmentVariable(string $variable_name): string {
        return $this->environment_variables[$variable_name];
    }

    /** @return array<string, string> */
    public function install(string $public_path): array {
        $this->installed_to = $public_path;
        return ['installed_to' => $public_path];
    }

    public function testOnlyGetUsername(): ?string {
        return $this->username;
    }

    public function testOnlyGetPassword(): ?string {
        return $this->password;
    }

    public function testOnlyGetTarget(): ?string {
        return $this->target;
    }

    public function testOnlySetTarget(?string $new_target): void {
        $this->target = $new_target;
    }

    public function testOnlyGetEnvironment(): ?string {
        return $this->environment;
    }

    public function testOnlySetEnvironment(?string $new_environment): void {
        $this->environment = $new_environment;
    }

    public function testOnlyGetRemotePublicRandomDeployDirname(): string {
        return parent::getRemotePublicRandomDeployDirname();
    }

    public function testOnlySetRemotePublicRandomDeployDirname(string $new): void {
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
        $fake_default_deploy->command_line_options = [];
        $fake_default_deploy->environment_variables = [];
        try {
            $fake_default_deploy->cli();
            throw new \Exception('Exception expected');
        } catch (\Throwable $th) {
            $this->assertSame('Command line option --target=... must be set.', $th->getMessage());
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
