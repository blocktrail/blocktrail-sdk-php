<?php

namespace Blocktrail\SDK\Services;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\UnspentOutputFinder;

class BlocktrailBatchUnspentOutputFinder extends UnspentOutputFinder {

    protected $client;
    protected $retryLimit;
    protected $sleepTime;
    protected $paginationLimit = 200;   //max results to retrieve at a time

    /**
     * @param        $apiKey
     * @param        $apiSecret
     * @param string $network
     * @param bool   $testnet
     * @param string $apiVersion
     * @param null   $apiEndpoint
     */
    public function __construct($apiKey, $apiSecret, $network = 'BTC', $testnet = false, $apiVersion = 'v1', $apiEndpoint = null) {
        $this->client = new BlocktrailSDK($apiKey, $apiSecret, $network, $testnet, $apiVersion, $apiEndpoint);

        $this->retryLimit = 5;
        $this->sleepTime = 20;
    }

    /**
     * modify the default limit on how many utxo results are returned per page
     * @param $limit
     */
    public function setPaginationLimit($limit) {
        $this->paginationLimit = $limit;
    }

    public function getUTXOs(array $addresses) {
        $results = array();

        foreach (array_chunk($addresses, 500) as $addresses) {
            if ($this->debug) {
                echo "\nchecking " . count($addresses) . " addresses ...";
            }
            //get the utxos for this address
            $utxos = $this->getUnspentOutputs($addresses);

            if (count($utxos) > 0) {
                $results = array_merge($results, $utxos);
            }
        }

        return $results;
    }

    /**
     * gets unspent outputs for a batch of addresses, returning and array of outputs with hash, index, value, and script pub hex
     *
     * @param string[] $addresses
     * @return array        2d array of unspent outputs as ['hash' => $hash, 'index' => $index, 'value' => $value, 'address' => $address, 'script_hex' => $scriptHex]
     * @throws \Exception
     */
    protected function getUnspentOutputs($addresses) {
        //get unspent outputs for the address - required data: hash, index, value, and script hex
        $utxos = array();
        $retries = 0;

        $page = 1;

        do {
            $more = true;

            try {
                $results = $this->client->batchAddressUnspentOutputs($addresses, $page, $this->paginationLimit);
                $utxos = array_merge($utxos, $results['data']);
                $page++;

                $more = count($results['data']) > 0;
            } catch (\Exception $e) {
                //if rate limit hit, sleep for a short while and try again
                if ($retries < $this->retryLimit) {
                    $retries++;
                    sleep($this->sleepTime);
                } else {
                    throw $e;
                }
            }
        } while ($more);

        //reduce the returned data into the values we're interested in
        $result = array_map(function ($utxo) {
            return array(
                'hash'       => $utxo['hash'],
                'index'      => $utxo['index'],
                'value'      => $utxo['value'],
                'address'    => $utxo['address'],
                'script_hex' => $utxo['script_hex'],
            );
        }, $utxos);

        return $result;
    }
}
