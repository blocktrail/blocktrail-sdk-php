<?php

namespace Blocktrail\SDK\Tests;

use Blocktrail\SDK\Throttler;
use PHPUnit\Framework\TestCase;

class ThrottlerTest extends TestCase
{
    private function runThrottlerCheckTime($time) {
        $t = new Throttler($time);
        $t->waitForThrottle();
        $then = microtime(true);
        $t->waitForThrottle();
        $diff = \microtime(true) - $then;
        $this->assertGreaterThanOrEqual($time, $diff);
    }

    public function testThrottler() {
        $this->runThrottlerCheckTime(1);
        $this->runThrottlerCheckTime(2);
    }
}
