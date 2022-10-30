<?php

// Will exist when deployed for integration test.
require_once __DIR__.'/vendor/autoload.php';

class Deploy {
    use \Psr\Log\LoggerAwareTrait;

    // In real-world usages, \Deploy extends PhpDeploy\AbstractDeploy, which
    // implements this function.
    // It is unfortunately unavailable via autoload in this integration test.

    public function injectRemoteLogger($remote_logger) {
        require_once __DIR__.'/../../../../../../../lib/RemoteDeployLoggerWrapper.php';
        $remote_logger_wrapper = new PhpDeploy\RemoteDeployLoggerWrapper($remote_logger);
        $this->logger = $remote_logger_wrapper;
    }

    // In real-world usages, \Deploy extends PhpDeploy\AbstractDeploy, which
    // implements this function.
    // It is unfortunately unavailable via autoload in this integration test.
    public function injectArgs($args) {
        $this->remote_public_random_deploy_dirname =
            $args['remote_public_random_deploy_dirname'];
    }

    public function install($public_path) {
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $private_path = __DIR__;
        $this->logger->info("From install method: Copying stuff...");
        $is_match = (bool) preg_match('/^[\\S]{24}$/', $this->remote_public_random_deploy_dirname);
        $this->logger->info("Copied args? {$is_match}");
        $fs->copy("{$private_path}/test.txt", "{$public_path}/index.txt", true);
    }
}
