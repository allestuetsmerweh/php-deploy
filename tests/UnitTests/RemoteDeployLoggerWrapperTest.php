<?php

declare(strict_types=1);

namespace PhpDeploy\Tests\UnitTests;

use PhpDeploy\RemoteDeployLogger;
use PhpDeploy\RemoteDeployLoggerWrapper;
use PhpDeploy\Tests\UnitTests\Common\UnitTestCase;

/**
 * @internal
 *
 * @covers \PhpDeploy\RemoteDeployLoggerWrapper
 */
final class RemoteDeployLoggerWrapperTest extends UnitTestCase {
    public function testMessage(): void {
        $logger = new RemoteDeployLogger();
        $logger_wrapper = new RemoteDeployLoggerWrapper($logger);
        $logger_wrapper->info('info-message', ['some', 'context']);
        $this->assertSame(1, count($logger->messages));
        $this->assertSame('info', $logger->messages[0]['level']);
        $this->assertSame('info-message', $logger->messages[0]['message']);
        $this->assertSame(['some', 'context'], $logger->messages[0]['context']);
        $this->assertGreaterThan(microtime(true) - 10, $logger->messages[0]['timestamp']);
        $this->assertLessThan(microtime(true), $logger->messages[0]['timestamp']);
    }

    public function testLevels(): void {
        $logger = new RemoteDeployLogger();
        $logger_wrapper = new RemoteDeployLoggerWrapper($logger);
        $logger_wrapper->emergency('emergency-message', []);
        $logger_wrapper->alert('alert-message', []);
        $logger_wrapper->critical('critical-message', []);
        $logger_wrapper->error('error-message', []);
        $logger_wrapper->warning('warning-message', []);
        $logger_wrapper->notice('notice-message', []);
        $logger_wrapper->info('info-message', []);
        $logger_wrapper->debug('debug-message', []);
        $this->assertSame([
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
            'notice',
            'info',
            'debug',
        ], array_map(
            function ($message) {
                return $message['level'];
            },
            $logger->messages
        ));
    }
}
