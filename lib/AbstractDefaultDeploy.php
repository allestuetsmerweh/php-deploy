<?php

namespace PhpDeploy;

abstract class AbstractDefaultDeploy extends AbstractDeploy {
    protected $environment;
    protected $username;
    protected $password;

    public function cli() {
        $class = get_called_class();
        $opts = $this->getCommandLineOptions();
        $environment = $opts['environment'];
        $username = $opts['username'];
        $password = $this->getEnvironmentVariable('PASSWORD');
        $this->environment = $environment;
        $this->username = $username;
        $this->password = $password;
        $this->buildAndDeploy();
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
