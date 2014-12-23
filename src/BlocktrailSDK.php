<?php

namespace Blocktrail\SDK;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BIP39\BIP39;
use BitWasp\BitcoinLib\BitcoinLib;
use BitWasp\BitcoinLib\RawTransaction;
use Blocktrail\SDK\Bitcoin\BIP44;
use Blocktrail\SDK\Connection\RestClient;

class BlocktrailSDK {
    /**
     * @var Connection\RestClient
     */
    protected $client;

    /**
     * @var string          currently only supporting; bitcoin
     */
    protected $network;

    /**
     * @var bool
     */
    protected $testnet;

    protected $autoWalletUpgrade = true;

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

            $apiEndpoint = getenv('BLOCKTRAIL_SDK_API_ENDPOINT') ?: "https://api.blocktrail.com";
            $apiEndpoint = "{$apiEndpoint}/{$apiVersion}/{$network}/";
        }

        list($this->network, $this->testnet) = $this->normalizeNetwork($network, $testnet);

        $this->setBitcoinLibMagicBytes($this->network, $this->testnet);

        $this->client = new RestClient($apiEndpoint, $apiVersion, $apiKey, $apiSecret);
    }

    protected function normalizeNetwork($network, $testnet) {
        switch (strtolower($network)) {
            case 'btc':
            case 'bitcoin':
                $network = 'bitcoin';

                break;

            case 'tbtc':
            case 'bitcoin-testnet':
                $network = 'bitcoin';
                $testnet = true;

                break;

            default:
                throw new \Exception("Unknown network [{$network}]");
        }

        return [$network, $testnet];
    }

    protected function setBitcoinLibMagicBytes($network, $testnet) {
        BitcoinLib::setMagicByteDefaults($network . ($testnet ? '-testnet' : ''));
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
     * enable verbose errors
     *
     * @param   bool        $verboseErrors
     */
    public function setVerboseErrors($verboseErrors = true) {
        $this->client->setVerboseErrors($verboseErrors);
    }
    
    /**
     * set cURL default option on Guzzle client
     * @param string    $key
     * @param bool      $value
     */
    public function setCurlDefaultOption($key, $value) {
        $this->client->setCurlDefaultOption($key, $value);
    }

    /**
     * @return  RestClient
     */
    public function getRestClient() {
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

        $response = $this->client->post("address/{$address}/verify", null, $postData, 'http-signatures');

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
        $response = $this->client->post("webhook", null, $postData, 'http-signatures');
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
        $response = $this->client->put("webhook/{$identifier}", null, $putData, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * deletes an existing webhook and any event subscriptions associated with it
     * @param  string  $identifier      the unique identifier of the webhook to delete
     * @return boolean                  true on success
     */
    public function deleteWebhook($identifier) {
        $response = $this->client->delete("webhook/{$identifier}", null, null, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * get a paginated list of all the events a webhook is subscribed to
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
        $response = $this->client->post("webhook/{$identifier}/events", null, $postData, 'http-signatures');
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
        $response = $this->client->post("webhook/{$identifier}/events", null, $postData, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * removes an address transaction event subscription from a webhook
     * @param  string  $identifier      the unique identifier of the webhook associated with the event subscription
     * @param  string  $address         the address hash of the event subscription
     * @return boolean                  true on success
     */
    public function unsubscribeAddressTransactions($identifier, $address) {
        $response = $this->client->delete("webhook/{$identifier}/address-transactions/{$address}", null, null, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * removes a block event subscription from a webhook
     * @param  string  $identifier      the unique identifier of the webhook associated with the event subscription
     * @return boolean                  true on success
     */
    public function unsubscribeNewBlocks($identifier) {
        $response = $this->client->delete("webhook/{$identifier}/block", null, null, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * @param      $identifier
     * @param      $password
     * @param int  $account         override for the account to use
     * @return array[Wallet, (string)backupMnemonic]
     * @throws \Exception
     */
    public function createNewWallet($identifier, $password, $account = 0) {
        list($primaryMnemonic, $primarySeed, $primaryPrivateKey) = $this->newPrimarySeed($password);
        $primaryPublicKey = BIP32::extended_private_to_public(BIP32::build_key($primaryPrivateKey, (string)BIP44::BIP44(($this->testnet ? 1 : 0), $account)->accountPath()));

        list($backupMnemonic, $backupSeed, $backupPrivateKey) = $this->newBackupSeed();
        $backupPublicKey = BIP32::extract_public_key($backupPrivateKey);

        $checksum = $this->createChecksum($primaryPrivateKey);

        $result = $this->_createNewWallet($identifier, $primaryPublicKey, $backupPublicKey, $primaryMnemonic, $checksum);
        $blocktrailPublicKeys = $result['blocktrail_public_keys'];

        if (isset($result['upgrade_account'])) {
            $account = $result['upgrade_account'];

            $primaryPublicKey = BIP32::extended_private_to_public(BIP32::build_key($primaryPrivateKey, (string)BIP44::BIP44(($this->testnet ? 1 : 0), $account)->accountPath()));
            $this->upgradeAccount($identifier, $account, $primaryPublicKey);
        }

        return array(new Wallet($this, $identifier, $primaryPrivateKey, $backupPublicKey, $blocktrailPublicKeys, $account, $this->testnet), $backupMnemonic);
    }

    public function _createNewWallet($identifier, $primaryPublicKey, $backupPublicKey, $primaryMnemonic, $checksum) {
        $data = [
            'identifier' => $identifier,
            'primary_public_key' => $primaryPublicKey,
            'backup_public_key' => $backupPublicKey,
            'primary_mnemonic' => $primaryMnemonic,
            'checksum' => $checksum
        ];

        $response = $this->client->post("wallet", null, $data, 'http-signatures');
        return json_decode($response->body(), true);
    }

    public function upgradeAccount($identifier, $account, $primaryPublicKey) {
        $data = [
            'account' => $account,
            'primary_public_key' => $primaryPublicKey
        ];

        $response = $this->client->post("wallet/{$identifier}/upgrade", null, $data, 'http-signatures');
        return json_decode($response->body(), true)['upgraded'];
    }

    public function initWallet($identifier, $password) {
        $wallet = $this->getWallet($identifier);

        if (!$wallet) {
            throw new \Exception("Failed to get wallet");
        }

        $primaryMnemonic = $wallet['primary_mnemonic'];
        $checksum = $wallet['checksum'];
        $backupPublicKey = $wallet['backup_public_key'];
        $blocktrailPublicKeys = $wallet['blocktrail_public_keys'];
        $account = $wallet['account'];

        $primarySeed = BIP39::mnemonicToSeedHex($primaryMnemonic, $password);

        $primaryPrivateKey = BIP32::master_key($primarySeed, $this->network, $this->testnet);

        // create checksum (address) of the primary privatekey to compare to the store checksum
        $checksum2 = $this->createChecksum($primaryPrivateKey);
        if ($checksum2 != $checksum) {
            throw new \Exception("Checksum [{$checksum}] does not match [{$checksum2}], most likely due to incorrect password");
        }

        if ($this->autoWalletUpgrade && isset($wallet['upgrade_account'])) {
            $account = $wallet['upgrade_account'];
            $primaryPublicKey = BIP32::extended_private_to_public(BIP32::build_key($primaryPrivateKey, (string)BIP44::BIP44(($this->testnet ? 1 : 0), $account)->accountPath()));
            $this->upgradeAccount($identifier, $account, $primaryPublicKey);
        }

        return new Wallet($this, $identifier, $primaryPrivateKey, $backupPublicKey, $blocktrailPublicKeys, $account, $this->testnet);
    }

    /**
     * generate a 'checksum' of our private key to validate that our mnemonic was correctly decoded
     *  we just generate the address based on the master PK as checksum
     *
     * @param $primaryPrivateKey
     * @return bool|string
     */
    protected function createChecksum($primaryPrivateKey) {
        return BIP32::key_to_address($primaryPrivateKey[0]);
    }

    public function getWallet($identifier) {
        $response = $this->client->get("wallet/{$identifier}", null, 'http-signatures');
        return json_decode($response->body(), true);
    }

    public function deleteWallet($identifier, $checksumAddress, $signature) {
        $response = $this->client->delete("wallet/{$identifier}", null, [
            'checksum' => $checksumAddress,
            'signature' => $signature
        ], 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * create new backup private key
     *
     * @return string
     */
    protected function newBackupSeed() {
        list($backupMnemonic, $backupSeed, $backupPrivateKey) = $this->generateNewSeed("");

        return [$backupMnemonic, $backupSeed, $backupPrivateKey];
    }

    /**
     * create new primary private key
     *
     * @param $passphrase
     * @return string
     */
    protected function newPrimarySeed($passphrase) {
        list($primaryMnemonic, $primarySeed, $primaryPrivateKey) = $this->generateNewSeed($passphrase);

        return [$primaryMnemonic, $primarySeed, $primaryPrivateKey];
    }

    protected function generateNewMnemonic($forceEntropy = null) {
        if (!$forceEntropy) {
            $entropy = BIP39::generateEntropy(512);
        }

        return BIP39::entropyToMnemonic($entropy);
    }

    protected function generateNewSeed($passphrase = "", $forceEntropy = null) {
        // generate master seed, retry if the generated private key isn't valid (FALSE is returned)
        do {
            $mnemonic = $this->generateNewMnemonic($forceEntropy);

            $seed = BIP39::mnemonicToSeedHex($mnemonic, $passphrase);

            $key = BIP32::master_key($seed, $this->network, $this->testnet);
        } while (!$key);

        return [$mnemonic, $seed, $key];
    }

    public function getWalletBalance($identifier) {
        $response = $this->client->get("wallet/{$identifier}/balance", null, 'http-signatures');
        return json_decode($response->body(), true);
    }

    public function doWalletDiscovery($identifier) {
        $response = $this->client->get("wallet/{$identifier}/discovery", null, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * get a new derivation number for specified identifier
     *
     * @param $identifier
     * @param $path
     * @return mixed
     */
    public function getNewDerivation($identifier, $path) {
        $result = $this->_getNewDerivation($identifier, $path);
        return $result['path'];
    }

    public function _getNewDerivation($identifier, $path) {
        $response = $this->client->post("wallet/{$identifier}/path", null, ['path' => $path], 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * send the transaction using the API
     *
     * @param $identifier
     * @param $rawTransaction
     * @param $paths
     * @return string           the complete raw transaction
     * @throws \Exception
     */
    public function sendTransaction($identifier, $rawTransaction, $paths) {
        $data = [
            'raw_transaction' => $rawTransaction,
            'paths' => $paths
        ];

        $response = $this->client->post("wallet/{$identifier}/send", null, $data, 'http-signatures');
        $signed = json_decode($response->body(), true);

        if (!$signed['complete'] || $signed['complete'] == 'false') {
            throw new \Exception("Failed to completely sign transaction");
        }

        return RawTransaction::hash_from_raw($signed['hex']);
    }

    /**
     * use the API to get the best inputs to use based on the outputs
     *
     * @param $identifier
     * @param $outputs
     * @param $lockUTXO
     * @return array
     */
    public function coinSelection($identifier, $outputs, $lockUTXO = false) {
        $response = $this->client->post("wallet/{$identifier}/coin-selection", ['lock' => (int)!!$lockUTXO], $outputs, 'http-signatures');
        return json_decode($response->body(), true);
    }

    /**
     * convert a Satoshi value to a BTC value
     *
     * @param int       $satoshi
     * @return float
     */
    public static function toBTC($satoshi) {
        return bcdiv((int)(string)$satoshi, 100000000, 8);
    }

    /**
     * convert a Satoshi value to a BTC value and return it as a string

     * @param int       $satoshi
     * @return string
     */
    public static function toBTCString($satoshi) {
        return sprintf("%.8f", self::toBTC($satoshi));
    }

    /**
     * convert a BTC value to a Satoshi value
     *
     * @param float     $btc
     * @return string
     */
    public static function toSatoshi($btc) {
        return bcmul(sprintf("%.8f", (float)$btc), 100000000, 0);
    }
}
