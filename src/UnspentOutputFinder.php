<?php

namespace Blocktrail\SDK;

class UnspentOutputFinder {

    /**
     * provides access to bitcoin data
     * @var BlockchainDataServiceInterface
     */
    protected $client;

    /**
     * process logging for debugging
     * @var bool
     */
    protected $debug = false;

    /**
     * @param BlockchainDataServiceInterface $bitcoinClient
     */
    public function __construct(BlockchainDataServiceInterface $bitcoinClient) {
        $this->client = $bitcoinClient;
    }

    /**
     * get unspent outputs for an array of addresses
     *
     * @param array $addresses
     * @return array    array of unspent outputs for each address with a spendable balance
     */
    public function getUTXOs(array $addresses) {
        $results = array();

        foreach ($addresses as $address) {
            if ($this->debug) {
                echo "\nchecking $address";
            }
            //get the utxos for this address
            $utxos = $this->client->getUnspentOutputs($address);

            if (count($utxos) > 0) {
                $results[$address] = $utxos;
            }
        }

        return $results;
    }

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
