<?php

namespace Blocktrail\SDK;

class Throttler {
    /**
     * @var Throttler[]
     */
    private static $instances = [];

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
            usleep((int)ceil($diff * 1e6));
        }

        $this->lastTime = \microtime(true);
    }

    public static function getInstance($key, $interval) {
        if (!array_key_exists($key, self::$instances)) {
            self::$instances[$key] = new Throttler($interval);
        }

        return self::$instances[$key];
    }
}
