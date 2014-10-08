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
     * get a single address
     * @param  string $address address hash
     * @return array           associative array containing the response
     */
    public function address($address) {
        $response = $this->client->get("address/{$address}");
        return json_decode($response->body(), true);
    }

    /**
     * get all transactions for an address (paginated)
     * @param  string  $address address hash
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page (max 500)
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function addressTransactions($address, $page=1, $limit=20, $sortDir='asc') {
        $queryString = array(
            'page'     => $page,
            'limit'    => $limit,
            'sort_dir' => $sortDir  
        );
        $response = $this->client->get("address/{$address}/transactions", $queryString);
        return json_decode($response->body(), true);
    }

    /**
     * get all unconfirmed transactions for an address (paginated)
     * @param  string  $address address hash
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page (max 500)
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function addressUnconfirmedTransactions($address, $page=1, $limit=20, $sortDir='asc') {
        $queryString = array(
            'page'     => $page,
            'limit'    => $limit,
            'sort_dir' => $sortDir  
        );
        $response = $this->client->get("address/{$address}/unconfirmed-transactions", $queryString);
        return json_decode($response->body(), true);
    }

    /**
     * get all unspent outputs for an address (paginated)
     * @param  string  $address address hash
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page (max 500)
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function addressUnspentOutputs($address, $page=1, $limit=20, $sortDir='asc') {
        $queryString = array(
            'page'     => $page,
            'limit'    => $limit,
            'sort_dir' => $sortDir  
        );
        $response = $this->client->get("address/{$address}/unspent-outputs", $queryString);
        return json_decode($response->body(), true);
    }

    /**
     * verify ownership of an address
     * @param  string  $address     address hash
     * @param  string  $signature   a signed message (the address hash) using the private key of the address
     * @return array                associative array containing the response
     */
    public function verifyAddress($address, $signature) {
        $postData = array('signature' => $signature);

        $response = $this->client->post("address/{$address}/verify", $postData, 'http-signatures');

        return json_decode($response->body(), true);
    }

    /**
     * get all blocks (paginated)
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function blocksAll($page=1, $limit=20, $sortDir='asc') {
        $queryString = array(
            'page'     => $page,
            'limit'    => $limit,
            'sort_dir' => $sortDir  
        );
        $response = $this->client->get("all-blocks", $queryString);
        return json_decode($response->body(), true);
    }

    /**
     * get the latest block
     * @return array            associative array containing the response
     */
    public function blockLatest() {
        $response = $this->client->get("block/latest");
        return json_decode($response->body(), true);
    }

    /**
     * get an individual block
     * @param  string|integer $block    a block hash or a block height
     * @return array                    associative array containing the response
     */
    public function block($block) {
        $response = $this->client->get("block/{$block}");
        return json_decode($response->body(), true);
    }

    /**
     * get all transaction in a block (paginated)
     * @param  string|integer   $block   a block hash or a block height
     * @param  integer          $page    pagination: page number
     * @param  integer          $limit   pagination: records per page
     * @param  string           $sortDir pagination: sort direction (asc|desc)
     * @return array                     associative array containing the response
     */
    public function blockTransactions($block, $page=1, $limit=20, $sortDir='asc') {
        $queryString = array(
            'page'     => $page,
            'limit'    => $limit,
            'sort_dir' => $sortDir  
        );
        $response = $this->client->get("block/{$block}/transactions", $queryString);
        return json_decode($response->body(), true);
    }

    /**
     * get a single transaction
     * @param  string $txhash transaction hash
     * @return array          associative array containing the response
     */
    public function transaction($txhash) {
        $response = $this->client->get("transaction/{$txhash}");
        return json_decode($response->body(), true);
    }
}
