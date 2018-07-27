<?php

namespace Blocktrail\SDK;

class Throttler {

    /**
     * @var float|null
     */
    private $lastTime = null;

    /**
     * interval to wait in seconds, can be float
     *
     * @var float
     */
    private $interval;

    public function __construct($interval) {
        $this->interval = $interval;
    }

    public function waitForThrottle() {
        if (!$this->lastTime) {
            $this->lastTime = \microtime(true);
            return;
        }

        $diff = $this->interval - (\microtime(true) - $this->lastTime);

        if ($diff > 0) {
            usleep((int)ceil($diff * 1000 * 1000));
        }

        $this->lastTime = \microtime(true);
    }

    private static $instances = [];

    public static function getInstance($key, $interval) {
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new Throttler($interval);
        }

        return self::$instances[$key];
    }
}
