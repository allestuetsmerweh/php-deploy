<?php

class Deploy {
    public $remote_logger_injected;
    public $args_injected;

    public function injectRemoteLogger($remote_logger) {
        $this->remote_logger_injected = $remote_logger;
        $this->remote_logger_injected->info('Logger injected');
    }

    public function injectArgs($args) {
        $this->args_injected = $args;
        if ($this->remote_logger_injected !== null) {
            $args_json = json_encode($args);
            $this->remote_logger_injected->info("Args injected: {$args_json}");
        }
    }

    public function install($public_path) {
        file_put_contents(__DIR__.'/installed_to.txt', $public_path);
    }
}
