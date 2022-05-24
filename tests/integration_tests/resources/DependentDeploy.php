<?php

// Will exist when deployed for integration test.
require_once __DIR__.'/vendor/autoload.php';

class Deploy {
    use \Psr\Log\LoggerAwareTrait;

    public function injectRemoteLogger($remote_logger) {
        $this->logger = $remote_logger;
    }

    public function install($public_path) {
        $fs = new Symfony\Component\Filesystem\Filesystem();
        $private_path = __DIR__;
        $this->logger->info("From install method: Copying stuff...");
        $fs->copy("{$private_path}/test.txt", "{$public_path}/index.txt", true);
    }
}
