<?php

namespace Blocktrail\SDK\Services;

use Blocktrail\SDK\BlockchainDataServiceInterface;
use Blocktrail\SDK\BlocktrailSDK;

class BlocktrailBitcoinService implements BlockchainDataServiceInterface {

    protected $client;
    protected $retryLimit;
    protected $sleepTime;
    protected $retries;

    public function __construct($apiKey, $apiSecret, $network = 'BTC', $testnet = false, $apiVersion = 'v1', $apiEndpoint = null) {
        $this->client = new BlocktrailSDK($apiKey, $apiSecret, $network, $testnet, $apiVersion, $apiEndpoint);

        $this->retryLimit = 5;
        $this->sleepTime = 20;
        $this->retries = 0;
    }

    public function getUnspentOutputs($address) {
        //get unspent outputs for the address - required data: hash, index, value, and script hex
        try {
            $utxos = $this->client->addressUnspentOutputs($address, $_page = 1, $_limit = 500);
        } catch (\Exception $e) {
            //if rate limit hit, sleep for a short while and try again
            if ($this->retries < $this->retryLimit) {
                $this->retries++;
                sleep($this->sleepTime);

                return $this->getUnspentOutputs($address);
            } else {
                throw $e;
            }
        }

        //reset retry count
        $this->retries = 0;

        //reduce the returned data into the values we're interested in
        $result = array_map(function ($utxo) {
            return array(
                'hash'       => $utxo['hash'],
                'index'      => $utxo['index'],
                'value'      => $utxo['value'],
                'script_hex' => $utxo['script_hex'],
            );
        }, $utxos['data']);

        return $result;
    }
}
