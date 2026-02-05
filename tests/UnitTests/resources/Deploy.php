<?php

use PhpDeploy\RemoteDeployLogger;

class Deploy {
    public ?RemoteDeployLogger $remote_logger_injected;
    /** @var array<string, string> */
    public array $args_injected;

    public function injectRemoteLogger(RemoteDeployLogger $remote_logger): void {
        $this->remote_logger_injected = $remote_logger;
        $this->remote_logger_injected->info('Logger injected');
    }

    /** @param array<string, string> $args */
    public function injectArgs(array $args): void {
        $this->args_injected = $args;
        if ($this->remote_logger_injected !== null) {
            $args_json = json_encode($args);
            $this->remote_logger_injected->info("Args injected: {$args_json}");
        }
    }

    /** @return array<string, string> */
    public function install(string $public_path): array {
        file_put_contents(__DIR__.'/installed_to.txt', $public_path);
        return [];
    }
}
