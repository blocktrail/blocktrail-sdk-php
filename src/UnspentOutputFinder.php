<?php

namespace Blocktrail\SDK;

abstract class UnspentOutputFinder {

    /**
     * process logging for debugging
     * @var bool
     */
    protected $debug = false;

    /**
     * get unspent outputs for an array of addresses
     *
     * @param array $addresses
     * @return array    array of unspent outputs for each address with a spendable balance
     */
    abstract public function getUTXOs(array $addresses);

    /**
     * enable debug info logging (just to console)
     */
    public function enableLogging() {
        $this->debug = true;
    }

    /**
     * disable debug info logging
     */
    public function disableLogging() {
        $this->debug = false;
    }
}
