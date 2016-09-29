<?php

namespace Blocktrail\SDK\Services;

use Blocktrail\SDK\BlocktrailSDK;
use Blocktrail\SDK\UnspentOutputFinder;

class InsightUnspentOutputFinder extends UnspentOutputFinder {

    protected $testnet;

    protected $retryLimit;
    protected $sleepTime;

    /**
     * @param bool $testnet
     */
    public function __construct($testnet = false) {
        $this->testnet = $testnet;

        $this->retryLimit = 5;
        $this->sleepTime = 20;
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
        $utxos = [];
        $retries = 0;

        do {
            $more = true;

            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->post(
                    'https://' . ($this->testnet ? 'test-' : '') . 'insight.bitpay.com/api/addrs/utxo',
                    [
                        'json' => ['addrs' => implode(",", $addresses)],
                        'timeout' => 30,
                    ]
                );

                $json = json_decode($response->getBody(), true);

                $utxos = $json;

                $more = false;
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
                'hash'       => $utxo['txid'],
                'index'      => $utxo['vout'],
                'value'      => BlocktrailSDK::toSatoshi($utxo['amount']),
                'address'    => $utxo['address'],
                'script_hex' => $utxo['scriptPubKey'],
            );
        }, $utxos);

        return $result;
    }
}
