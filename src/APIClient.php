<?php

namespace BlockTrail\SDK;

use BlockTrail\SDK\Connection\RestClient;

/**
 * Class APIClient
 *
 * Final Class to use for consuming the BlockTrail API
 *
 * @package BlockTrail\SDK
 */
class APIClient {

    /**
     * @var Connection\RestClient
     */
    protected $client;

    /**
     * @param   string      $apiKey         the API_KEY to use for authentication
     * @param   string      $apiSecret      the API_SECRET to use for authentication
     * @param   string      $network        the cryptocurrency 'network' to consume, eg BTC, LTC, etc
     * @param   bool        $testnet        testnet yes/no
     * @param   string      $apiVersion     the version of the API to consume
     * @param   null        $apiEndpoint    overwrite the endpoint used
     *                                       this will cause the $network, $testnet and $apiVersion to be ignored!
     */
    public function __construct($apiKey, $apiSecret, $network = 'BTC', $testnet = false, $apiVersion = 'v1', $apiEndpoint = null) {
        if (is_null($apiEndpoint)) {
            $network = strtoupper($network);

            if ($testnet) {
                $network = "t{$network}";
            }

            $apiEndpoint = "http://api.blocktrail.ngrok.com/{$apiVersion}/{$network}/";
        }

        $this->client = new RestClient($apiEndpoint, $apiVersion, $apiKey, $apiSecret);
    }

    /**
     * enable CURL debugging output
     *
     * @param   bool        $debug
     */
    public function setCurlDebugging($debug = true) {
        $this->client->setCurlDebugging($debug);
    }

    /**
     * @return  RestClient
     */
    public function getClient() {
        return $this->client;
    }

    /**
     * @param   string      $address        the address hash to request
     * @return  array|string                returns an array unless a specific format is requested
     *                                       in which case it will return a string with the contents being in that format
     * @throws  InvalidFormat
     */
    public function address($address) {
        $response = $this->client->get("address/{$address}");

        return json_decode($response->body(), true);
    }

    /**
     * verify ownership of an address
     *
     * @param  string       $address        address hash
     * @param  string       $signature      a signed message (the address hash) using the private key of the address
     * @return array|string
     */
    public function verifyAddress($address, $signature) {
        $postData = array('signature' => $signature);

        $response = $this->client->post("address/{$address}/verify", $postData, 'http-signatures');

        return json_decode($response->body(), true);
    }
}
