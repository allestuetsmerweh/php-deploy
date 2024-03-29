<?php

namespace PhpDeploy;

abstract class AbstractDefaultDeploy extends AbstractDeploy implements \Psr\Log\LoggerAwareInterface {
    /**
     * A target corresponds to one remote server location.
     * You might have multiple targets, e.g. while moving hosters.
     */
    protected $target;

    /**
     * Within one target, there can be multiple instances: environments.
     * You might have environments like prod, staging, etc.
     */
    protected $environment;

    protected $username;

    protected $password;

    public function getArgs() {
        return [
            ...parent::getArgs(),
            'target' => $this->target,
            'environment' => $this->environment,
        ];
    }

    public function injectArgs($args) {
        parent::injectArgs($args);
        $this->target = $args['target'] ?? null;
        $this->environment = $args['environment'] ?? null;
    }

    public function cli() {
        $this->initFromEnv();
        $this->buildAndDeploy();
    }

    public function initFromEnv() {
        $opts = $this->getCommandLineOptions();
        $target = $opts['target'];
        $environment = $opts['environment'];
        $username = $opts['username'];
        $password = $this->getEnvironmentVariable('PASSWORD');

        $this->target = $target;
        $this->environment = $environment;
        $this->username = $username;
        $this->password = $password;
    }

    protected function getCommandLineOptions() {
        // @codeCoverageIgnoreStart
        // Reason: Hard to test side effects!
        return getopt('', ['target:', 'environment:', 'username:']);
        // @codeCoverageIgnoreEnd
    }

    protected function getEnvironmentVariable($variable_name) {
        // @codeCoverageIgnoreStart
        // Reason: Hard to test side effects!
        return getenv($variable_name, true);
        // @codeCoverageIgnoreEnd
    }
}
