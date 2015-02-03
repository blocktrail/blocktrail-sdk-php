<?php

namespace Blocktrail\SDK;

class UnspentOutputFinder {

    /**
     * @var BlockchainDataServiceInterface      provides access to bitcoin data
     */
    protected $client;

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

        foreach($addresses as $address) {
            //get the utxos for this address
            $utxos = $this->client->getUnspentOutputs($address);

            if (count($utxos) > 0) {
                $results[$address] = $utxos;
            }
        }

        return $results;
    }
}