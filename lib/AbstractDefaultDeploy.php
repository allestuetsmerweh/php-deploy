<?php

namespace PhpDeploy;

abstract class AbstractDefaultDeploy extends AbstractDeploy implements \Psr\Log\LoggerAwareInterface {
    protected $environment;
    protected $username;
    protected $password;

    public function getArgs() {
        return [
            ...parent::getArgs(),
            'environment' => $this->environment,
        ];
    }

    public function injectArgs($args) {
        parent::injectArgs($args);
        $this->environment = $args['environment'] ?? null;
    }

    public function cli() {
        $this->initFromEnv();
        $this->buildAndDeploy();
    }

    public function initFromEnv() {
        $opts = $this->getCommandLineOptions();
        $environment = $opts['environment'];
        $username = $opts['username'];
        $password = $this->getEnvironmentVariable('PASSWORD');

        $this->environment = $environment;
        $this->username = $username;
        $this->password = $password;
    }

    protected function getCommandLineOptions() {
        // @codeCoverageIgnoreStart
        // Reason: Hard to test side effects!
        return getopt('', ['environment:', 'username:']);
        // @codeCoverageIgnoreEnd
    }

    protected function getEnvironmentVariable($variable_name) {
        // @codeCoverageIgnoreStart
        // Reason: Hard to test side effects!
        return getenv($variable_name, true);
        // @codeCoverageIgnoreEnd
    }
}
