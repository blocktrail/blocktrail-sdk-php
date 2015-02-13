<?php

namespace Blocktrail\SDK\Services;

use Blocktrail\SDK\BlockchainDataServiceInterface;
use Blocktrail\SDK\BlocktrailSDK;

class BlocktrailBitcoinService implements BlockchainDataServiceInterface {

    protected $client;
    protected $retryLimit;
    protected $sleepTime;
    protected $retries;
    protected $paginationLimit = 500;   //max results to retrieve at a time

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
        $this->retries = 0;
    }

    /**
     * modify the default limit on how many utxo results are returned per page
     * @param $limit
     */
    public function setPaginationLimit($limit) {
        $this->paginationLimit = $limit;
    }

    /**
     * gets unspent outputs for an address, returning and array of outputs with hash, index, value, and script pub hex
     *
     * @param $address
     * @return array        2d array of unspent outputs as ['hash' => $hash, 'index' => $index, 'value' => $value, 'script_hex' => $scriptHex]
     * @throws \Exception
     */
    public function getUnspentOutputs($address) {
        //get unspent outputs for the address - required data: hash, index, value, and script hex
        $utxos = array();
        try {
            $page = 1;
            do {
                $results = $this->client->addressUnspentOutputs($address, $page, $this->paginationLimit);
                $utxos = array_merge($utxos, $results['data']);
                $page++;
            } while (count($results['data']) > 0);

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
        }, $utxos);

        return $result;
    }
}
