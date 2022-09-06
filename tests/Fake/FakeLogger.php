<?php

namespace PhpDeploy\Tests\Fake;

class FakeLogger extends \Psr\Log\AbstractLogger {
    public $messages = [];

    public function log($level, string|\Stringable $message, array $context = []): void {
        $this->messages[] = [$level, $message, $context];
    }
}
