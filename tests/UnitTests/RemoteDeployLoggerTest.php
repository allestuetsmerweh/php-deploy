<?php

declare(strict_types=1);

namespace PhpDeploy\Tests\UnitTests;

use PhpDeploy\RemoteDeployLogger;
use PhpDeploy\Tests\UnitTests\Common\UnitTestCase;

/**
 * @internal
 *
 * @covers \PhpDeploy\RemoteDeployLogger
 */
final class RemoteDeployLoggerTest extends UnitTestCase {
    public function testMessage(): void {
        $logger = new RemoteDeployLogger();
        $logger->info('info-message', ['some', 'context']);
        $this->assertSame(1, count($logger->messages));
        $this->assertSame('info', $logger->messages[0]['level']);
        $this->assertSame('info-message', $logger->messages[0]['message']);
        $this->assertSame(['some', 'context'], $logger->messages[0]['context']);
        $this->assertGreaterThan(microtime(true) - 10, $logger->messages[0]['timestamp']);
        $this->assertLessThan(microtime(true), $logger->messages[0]['timestamp']);
    }

    public function testLevels(): void {
        $logger = new RemoteDeployLogger();
        // @phpstan-ignore-next-line Intentional fallback.
        $logger->emergency('emergency-message', []);
        // @phpstan-ignore-next-line Intentional fallback.
        $logger->alert('alert-message', []);
        // @phpstan-ignore-next-line Intentional fallback.
        $logger->critical('critical-message', []);
        // @phpstan-ignore-next-line Intentional fallback.
        $logger->error('error-message', []);
        // @phpstan-ignore-next-line Intentional fallback.
        $logger->warning('warning-message', []);
        // @phpstan-ignore-next-line Intentional fallback.
        $logger->notice('notice-message', []);
        $logger->info('info-message', []);
        // @phpstan-ignore-next-line Intentional fallback.
        $logger->debug('debug-message', []);
        // @phpstan-ignore-next-line Intentional fallback.
        $logger->invalid('invalid-message', []);
        $this->assertSame([
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
            'notice',
            'info',
            'debug',
            'invalid',
        ], array_map(
            function ($message) {
                return $message['level'];
            },
            $logger->messages
        ));
    }
}
