<?php

namespace PhpDeploy\Tests\Fake;

class FakeLogger extends \Psr\Log\AbstractLogger {
    /** @var array<array{0: mixed, 1: string|\Stringable, 2: array<mixed>}> */
    public array $messages = [];

    /**
     * @param string       $level
     * @param array<mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void {
        $this->messages[] = [$level, $message, $context];
    }
}
