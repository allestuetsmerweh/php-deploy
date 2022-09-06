<?php

namespace PhpDeploy;

class RemoteDeployLoggerWrapper extends \Psr\Log\AbstractLogger {
    protected $remote_logger = [];

    public function __construct($remote_logger) {
        $this->remote_logger = $remote_logger;
    }

    public function log($level, $message, array $context = []) {
        $this->remote_logger->log($level, $message, $context);
    }
}
