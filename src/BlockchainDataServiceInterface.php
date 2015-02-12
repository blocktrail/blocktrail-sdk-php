<?php

namespace Blocktrail\SDK;

interface BlockchainDataServiceInterface {

    /**
     * gets unspent outputs for an address, returning and array of outputs with hash, index, value, and script pub hex
     *
     * @param $address
     * @return array    2d array of unspent outputs as ['hash' => $hash, 'index' => $index, 'value' => $value, 'script_hex' => $scriptHex]
     */
    public function getUnspentOutputs($address);
}
