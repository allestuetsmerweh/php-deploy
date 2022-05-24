<?php

class FakeLogger extends Psr\Log\AbstractLogger {
    public $messages = [];

    public function log($level, $message, array $context = []) {
        $this->messages[] = [$level, $message, $context];
    }
}
