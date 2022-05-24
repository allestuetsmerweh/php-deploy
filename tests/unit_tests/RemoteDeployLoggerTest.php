<?php

declare(strict_types=1);

use PhpDeploy\RemoteDeployLogger;

require_once __DIR__.'/_common/UnitTestCase.php';

/**
 * @internal
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
        $logger->emergency('emergency-message', []);
        $logger->alert('alert-message', []);
        $logger->critical('critical-message', []);
        $logger->error('error-message', []);
        $logger->warning('warning-message', []);
        $logger->notice('notice-message', []);
        $logger->info('info-message', []);
        $logger->debug('debug-message', []);
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
