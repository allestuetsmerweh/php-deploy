<?php

namespace PhpDeploy;

abstract class AbstractDefaultDeploy extends AbstractDeploy implements \Psr\Log\LoggerAwareInterface {
    /**
     * A target corresponds to one remote server location.
     * You might have multiple targets, e.g. while moving hosters.
     */
    protected ?string $target = null;

    /**
     * Within one target, there can be multiple instances: environments.
     * You might have environments like prod, staging, etc.
     */
    protected ?string $environment = null;

    protected ?string $username = null;

    protected ?string $password = null;

    /** @return array<string, string> */
    public function getArgs(): array {
        return [
            ...parent::getArgs(),
            'target' => $this->target ?? '',
            'environment' => $this->environment ?? '',
        ];
    }

    /** @param array<string, string> $args */
    public function injectArgs(array $args): void {
        parent::injectArgs($args);
        $this->target = $args['target'] ?? null;
        $this->environment = $args['environment'] ?? null;
    }

    public function cli(): void {
        $this->initFromEnv();
        $this->buildAndDeploy();
    }

    public function initFromEnv(): void {
        $opts = $this->getCommandLineOptions();
        $target = $opts['target'] ?? null;
        if (!is_string($target)) {
            throw new \Exception("Command line option --target=... must be set.");
        }
        $environment = $opts['environment'] ?? null;
        if (!is_string($environment)) {
            throw new \Exception("Command line option --environment=... must be set.");
        }
        $username = $opts['username'] ?? null;
        if (!is_string($username)) {
            throw new \Exception("Command line option --username=... must be set.");
        }
        $password = $this->getEnvironmentVariable('PASSWORD');
        if (!$password) {
            throw new \Exception("Environment variable PASSWORD=... must be set.");
        }

        $this->target = $target;
        $this->environment = $environment;
        $this->username = $username;
        $this->password = $password;
    }

    /** @return array<string, array<int, mixed>|bool|string> */
    protected function getCommandLineOptions(): array {
        // @codeCoverageIgnoreStart
        // Reason: Hard to test side effects!
        $opts = getopt('', ['target:', 'environment:', 'username:']);
        if (!$opts) {
            return [];
        }
        return $opts;
        // @codeCoverageIgnoreEnd
    }

    protected function getEnvironmentVariable(string $variable_name): false|string {
        // @codeCoverageIgnoreStart
        // Reason: Hard to test side effects!
        $value = getenv($variable_name, true);
        if (!is_string($value)) {
            return false;
        }
        return $value;
        // @codeCoverageIgnoreEnd
    }
}
