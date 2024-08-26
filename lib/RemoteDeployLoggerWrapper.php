<?php

namespace PhpDeploy;

class RemoteDeployLoggerWrapper extends \Psr\Log\AbstractLogger {
    protected RemoteDeployLogger $remote_logger;

    public function __construct(RemoteDeployLogger $remote_logger) {
        $this->remote_logger = $remote_logger;
    }

    public function log($level, string|\Stringable $message, array $context = []): void {
        $this->remote_logger->log($level, $message, $context);
    }
}
