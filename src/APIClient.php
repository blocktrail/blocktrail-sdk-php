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

            $apiEndpoint = "https://api.blocktrail.com/{$apiVersion}/{$network}/";
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
    public function addressTransactions($address, $page = 1, $limit = 20, $sortDir = 'asc') {
        $queryString = array(
            'page' => $page,
            'limit' => $limit,
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
    public function addressUnconfirmedTransactions($address, $page = 1, $limit = 20, $sortDir = 'asc') {
        $queryString = array(
            'page' => $page,
            'limit' => $limit,
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
    public function addressUnspentOutputs($address, $page = 1, $limit = 20, $sortDir = 'asc') {
        $queryString = array(
            'page' => $page,
            'limit' => $limit,
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
    public function allBlocks($page = 1, $limit = 20, $sortDir = 'asc') {
        $queryString = array(
            'page' => $page,
            'limit' => $limit,
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
    public function blockTransactions($block, $page = 1, $limit = 20, $sortDir = 'asc') {
        $queryString = array(
            'page' => $page,
            'limit' => $limit,
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

    /**
     * get a paginated list of all webhooks associated with the api user
     * @param  integer          $page    pagination: page number
     * @param  integer          $limit   pagination: records per page
     * @return array                     associative array containing the response
     */
    public function allWebhooks($page = 1, $limit = 20) {
        $queryString = array(
            'page' => $page,
            'limit' => $limit
        );
        $response = $this->client->get("webhooks", $queryString);
        return json_decode($response->body(), true);
    }

    /**
     * get an existing webhook by it's identifier
     * @param string    $identifier     a unique identifier associated with the webhook
     * @return array                    associative array containing the response
     */
    public function getWebhook($identifier) {
        $response = $this->client->get("webhook/".$identifier);
        return json_decode($response->body(), true);
    }

    /**
     * create a new webhook
     * @param  string  $url        the url to receive the webhook events
     * @param  string  $identifier a unique identifier to associate with this webhook (optional)
     * @return array               associative array containing the response
     */
    public function setupWebhook($url, $identifier = null) {
        $postData = array(
            'url'        => $url,
            'identifier' => $identifier
        );
        $response = $this->client->post("webhook", $postData, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * update an existing webhook
     * @param  string  $identifier      the unique identifier of the webhook to update
     * @param  string  $newUrl          the new url to receive the webhook events (optional)
     * @param  string  $newIdentifier   a new unique identifier to associate with this webhook (optional)
     * @return array                    associative array containing the response
     */
    public function updateWebhook($identifier, $newUrl = null, $newIdentifier = null) {
        $putData = array(
            'url'        => $newUrl,
            'identifier' => $newIdentifier
        );
        $response = $this->client->put("webhook/{$identifier}", $putData, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * deletes an existing webhook and any event subscriptions associated with it
     * @param  string  $identifier      the unique identifier of the webhook to delete
     * @return boolean                  true on success
     */
    public function deleteWebhook($identifier) {
        $response = $this->client->delete("webhook/{$identifier}", 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * @param  string  $identifier  the unique identifier of the webhook
     * @param  integer $page        pagination: page number
     * @param  integer $limit       pagination: records per page
     * @return array                associative array containing the response
     */
    public function getWebhookEvents($identifier, $page = 1, $limit = 20) {
        $queryString = array(
            'page' => $page,
            'limit' => $limit
        );
        $response = $this->client->get("webhook/{$identifier}/events", $queryString);
        return json_decode($response->body(), true);
    }

    /**
     * subscribes a webhook to transaction events on a particular address
     * @param  string  $identifier      the unique identifier of the webhook to be triggered
     * @param  string  $address         the address hash
     * @param  integer $confirmations   the amount of confirmations to send.
     * @return array                    associative array containing the response
     */
    public function subscribeAddressTransactions($identifier, $address, $confirmations = 6) {
        $postData = array(
            'event_type'    => 'address-transactions',
            'address'       => $address,
            'confirmations' => $confirmations,
        );
        $response = $this->client->post("webhook/{$identifier}/events", $postData, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * subscribes a webhook to a new block event
     * @param  string  $identifier  the unique identifier of the webhook to be triggered
     * @return array                associative array containing the response
     */
    public function subscribeNewBlocks($identifier) {
        $postData = array(
            'event_type'    => 'block',
        );
        $response = $this->client->post("webhook/{$identifier}/events", $postData, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * removes an address transaction event subscription from a webhook
     * @param  string  $identifier      the unique identifier of the webhook associated with the event subscription
     * @param  string  $address         the address hash of the event subscription
     * @return boolean                  true on success
     */
    public function unsubscribeAddressTransactions($identifier, $address) {
        $response = $this->client->delete("webhook/{$identifier}/address-transactions/{$address}", 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * removes a block event subscription from a webhook
     * @param  string  $identifier      the unique identifier of the webhook associated with the event subscription
     * @return boolean                  true on success
     */
    public function unsubscribeNewBlocks($identifier) {
        $response = $this->client->delete("webhook/{$identifier}/block", 'http-signatures');
        return json_decode($response->body(), true);
    }
}
