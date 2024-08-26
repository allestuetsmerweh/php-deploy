<?php

use PhpDeploy\RemoteDeployLogger;
use PhpDeploy\RemoteDeployLoggerWrapper;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Filesystem\Filesystem;

// Will exist when deployed for integration test.
require_once __DIR__.'/vendor/autoload.php';

class Deploy {
    use LoggerAwareTrait;

    protected ?string $remote_public_random_deploy_dirname = null;

    // In real-world usages, \Deploy extends PhpDeploy\AbstractDeploy, which
    // implements this function.
    // It is unfortunately unavailable via autoload in this integration test.

    public function injectRemoteLogger(RemoteDeployLogger $remote_logger): void {
        require_once __DIR__.'/../../../../../../../lib/RemoteDeployLoggerWrapper.php';
        $remote_logger_wrapper = new RemoteDeployLoggerWrapper($remote_logger);
        $this->logger = $remote_logger_wrapper;
    }

    // In real-world usages, \Deploy extends PhpDeploy\AbstractDeploy, which
    // implements this function.
    // It is unfortunately unavailable via autoload in this integration test.
    /** @param array<string, string> $args */
    public function injectArgs(array $args): void {
        $this->remote_public_random_deploy_dirname =
            $args['remote_public_random_deploy_dirname'];
    }

    /** @return array<string, string> */
    public function install(string $public_path): array {
        $fs = new Filesystem();
        $private_path = __DIR__;
        $this->logger?->info("From install method: Copying stuff...");
        $is_match = (bool) preg_match('/^[\S]{24}$/', $this->remote_public_random_deploy_dirname ?? '');
        $this->logger?->info("Copied args? {$is_match}");
        $fs->copy("{$private_path}/test.txt", "{$public_path}/index.txt", true);
        return [
            'file' => basename(__FILE__),
            'result' => 'dependent-deploy-result',
        ];
    }
}
