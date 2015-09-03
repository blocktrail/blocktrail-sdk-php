<?php

namespace Blocktrail\SDK;

use Blocktrail\SDK\Connection\RestClient;

/**
 * Interface BlocktrailSDK
 */
interface BlocktrailSDKInterface {

    public function __construct($apiKey, $apiSecret, $network = 'BTC', $testnet = false, $apiVersion = 'v1', $apiEndpoint = null);

    /**
     * enable CURL debugging output
     *
     * @param   bool        $debug
     *
     * @codeCoverageIgnore
     */
    public function setCurlDebugging($debug = true);

    /**
     * enable verbose errors
     *
     * @param   bool        $verboseErrors
     *
     * @codeCoverageIgnore
     */
    public function setVerboseErrors($verboseErrors = true);
    
    /**
     * set cURL default option on Guzzle client
     * @param string    $key
     * @param bool      $value
     *
     * @codeCoverageIgnore
     */
    public function setCurlDefaultOption($key, $value);

    /**
     * @return  RestClient
     */
    public function getRestClient();

    /**
     * get a single address
     * @param  string $address address hash
     * @return array           associative array containing the response
     */
    public function address($address);

    /**
     * get all transactions for an address (paginated)
     * @param  string  $address address hash
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page (max 500)
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function addressTransactions($address, $page = 1, $limit = 20, $sortDir = 'asc');

    /**
     * get all unconfirmed transactions for an address (paginated)
     * @param  string  $address address hash
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page (max 500)
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function addressUnconfirmedTransactions($address, $page = 1, $limit = 20, $sortDir = 'asc');

    /**
     * get all unspent outputs for an address (paginated)
     * @param  string  $address address hash
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page (max 500)
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function addressUnspentOutputs($address, $page = 1, $limit = 20, $sortDir = 'asc');

    /**
     * verify ownership of an address
     * @param  string  $address     address hash
     * @param  string  $signature   a signed message (the address hash) using the private key of the address
     * @return array                associative array containing the response
     */
    public function verifyAddress($address, $signature);

    /**
     * get all blocks (paginated)
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function allBlocks($page = 1, $limit = 20, $sortDir = 'asc');

    /**
     * get the latest block
     * @return array            associative array containing the response
     */
    public function blockLatest();

    /**
     * get an individual block
     * @param  string|integer $block    a block hash or a block height
     * @return array                    associative array containing the response
     */
    public function block($block);

    /**
     * get all transaction in a block (paginated)
     * @param  string|integer   $block   a block hash or a block height
     * @param  integer          $page    pagination: page number
     * @param  integer          $limit   pagination: records per page
     * @param  string           $sortDir pagination: sort direction (asc|desc)
     * @return array                     associative array containing the response
     */
    public function blockTransactions($block, $page = 1, $limit = 20, $sortDir = 'asc');

    /**
     * get a single transaction
     * @param  string $txhash transaction hash
     * @return array          associative array containing the response
     */
    public function transaction($txhash);
    
    /**
     * get a paginated list of all webhooks associated with the api user
     * @param  integer          $page    pagination: page number
     * @param  integer          $limit   pagination: records per page
     * @return array                     associative array containing the response
     */
    public function allWebhooks($page = 1, $limit = 20);

    /**
     * get an existing webhook by it's identifier
     * @param string    $identifier     a unique identifier associated with the webhook
     * @return array                    associative array containing the response
     */
    public function getWebhook($identifier);

    /**
     * create a new webhook
     * @param  string  $url        the url to receive the webhook events
     * @param  string  $identifier a unique identifier to associate with this webhook
     * @return array               associative array containing the response
     */
    public function setupWebhook($url, $identifier = null);

    /**
     * update an existing webhook
     * @param  string  $identifier      the unique identifier of the webhook to update
     * @param  string  $newUrl          the new url to receive the webhook events
     * @param  string  $newIdentifier   a new unique identifier to associate with this webhook
     * @return array                    associative array containing the response
     */
    public function updateWebhook($identifier, $newUrl = null, $newIdentifier = null);

    /**
     * deletes an existing webhook and any event subscriptions associated with it
     * @param  string  $identifier      the unique identifier of the webhook to delete
     * @return boolean                  true on success
     */
    public function deleteWebhook($identifier);

    /**
     * get a paginated list of all the events a webhook is subscribed to
     * @param  string  $identifier  the unique identifier of the webhook
     * @param  integer $page        pagination: page number
     * @param  integer $limit       pagination: records per page
     * @return array                associative array containing the response
     */
    public function getWebhookEvents($identifier, $page = 1, $limit = 20);

    /**
     * subscribes a webhook to transaction events of one particular transaction
     * @param  string  $identifier      the unique identifier of the webhook to be triggered
     * @param  string  $transaction     the transaction hash
     * @param  integer $confirmations   the amount of confirmations to send.
     * @return array                    associative array containing the response
     */
    public function subscribeTransaction($identifier, $transaction, $confirmations = 6);

    /**
     * subscribes a webhook to transaction events on a particular address
     *
     * @param  string  $identifier      the unique identifier of the webhook to be triggered
     * @param  string  $address         the address hash
     * @param  integer $confirmations   the amount of confirmations to send.
     * @return array                    associative array containing the response
     */
    public function subscribeAddressTransactions($identifier, $address, $confirmations = 6);

    /**
     * subscribes a webhook to a new block event
     *
     * @param  string  $identifier  the unique identifier of the webhook to be triggered
     * @return array                associative array containing the response
     */
    public function subscribeNewBlocks($identifier);

    /**
     * removes an transaction event subscription from a webhook
     * @param  string  $identifier      the unique identifier of the webhook associated with the event subscription
     * @param  string  $transaction     the transaction hash of the event subscription
     * @return boolean                  true on success
     */
    public function unsubscribeTransaction($identifier, $transaction);

    /**
     * removes an address transaction event subscription from a webhook
     *
     * @param  string  $identifier      the unique identifier of the webhook associated with the event subscription
     * @param  string  $address         the address hash of the event subscription
     * @return boolean                  true on success
     */
    public function unsubscribeAddressTransactions($identifier, $address);

    /**
     * batch subscribes a webhook to multiple transaction events
     *
     * @param  string $identifier   the unique identifier of the webhook
     * @param  array  $batchData    A 2D array of event data:
     *                              [address => $address, confirmations => $confirmations]
     *                              where $address is the address to subscibe to
     *                              and optionally $confirmations is the amount of confirmations
     * @return boolean              true on success
     */
    public function batchSubscribeAddressTransactions($identifier, $batchData);

    /**
     * removes a block event subscription from a webhook
     * @param  string  $identifier      the unique identifier of the webhook associated with the event subscription
     * @return boolean                  true on success
     */
    public function unsubscribeNewBlocks($identifier);

    /**
     * create a new wallet
     *   - will generate a new primary seed (with password) and backup seed (without password)
     *   - send the primary seed (BIP39 'encrypted') and backup public key to the server
     *   - receive the blocktrail co-signing public key from the server
     *
     * Either takes one argument:
     * @param array $options
     *
     * Or takes three arguments (old, deprecated syntax):
     * (@nonPHP-doc) @param      $identifier
     * (@nonPHP-doc) @param      $password
     * (@nonPHP-doc) @param int  $keyIndex         override for the blocktrail cosigning key to use
     *
     * @return array[WalletInterface, (string)primaryMnemonic, (string)backupMnemonic]
     * @throws \Exception
     */
    public function createNewWallet($options);

    /**
     * create wallet using the API
     *
     * @param string    $identifier             the wallet identifier to create
     * @param array     $primaryPublicKey       BIP32 extended public key - [key, path]
     * @param string    $backupPublicKey        plain public key
     * @param string    $primaryMnemonic        mnemonic to store
     * @param string    $checksum               checksum to store
     * @param int       $keyIndex               account that we expect to use
     * @return mixed
     */
    public function _createNewWallet($identifier, $primaryPublicKey, $backupPublicKey, $primaryMnemonic, $checksum, $keyIndex);

    /**
     * upgrade wallet to use a new account number
     *  the account number specifies which blocktrail cosigning key is used
     *
     * @param string    $identifier             the wallet identifier to be upgraded
     * @param int       $keyIndex               the new account to use
     * @param array     $primaryPublicKey       BIP32 extended public key - [key, path]
     * @return mixed
     */
    public function upgradeKeyIndex($identifier, $keyIndex, $primaryPublicKey);

    /**
     * initialize a previously created wallet
     *
     * Either takes one argument:
     * @param array $options
     *
     * Or takes two arguments (old, deprecated syntax):
     * (@nonPHP-doc) @param string    $identifier             the wallet identifier to be initialized
     * (@nonPHP-doc) @param string    $password               the password to decrypt the mnemonic with
     *
     * @return Wallet
     * @throws \Exception
     */
    public function initWallet($options);

    /**
     * get the wallet data from the server
     *
     * @param string    $identifier             the identifier of the wallet to be retrieved
     * @return mixed
     */
    public function getWallet($identifier);

    /**
     * delete a wallet from the server
     *  the checksum address and a signature to verify you ownership of the key of that checksum address
     *  is required to be able to delete a wallet
     *
     * @param string    $identifier             the identifier of the wallet
     * @param string    $checksumAddress        the address for your master private key (and the checksum used when creating the wallet)
     * @param string    $signature              a signature of the checksum address as message signed by the private key matching that address
     * @param bool      $force                  ignore warnings (such as a non-zero balance)
     * @return mixed
     */
    public function deleteWallet($identifier, $checksumAddress, $signature, $force = false);

    /**
     * get the balance for the wallet
     *
     * @param string    $identifier             the identifier of the wallet
     * @return array
     */
    public function getWalletBalance($identifier);

    /**
     * do HD wallet discovery for the wallet
     *
     * @param string    $identifier             the identifier of the wallet
     * @param int       $gap                    the gap setting to use for discovery
     * @return mixed
     */
    public function doWalletDiscovery($identifier, $gap = 200);

    /**
     * get the path (and redeemScript) to specified address
     *
     * @param string $identifier
     * @param string $address
     * @return array
     * @throws \Exception
     */
    public function getPathForAddress($identifier, $address);

    /**
     * get a new derivation number for specified parent path
     *  eg; m/44'/1'/0/0 results in m/44'/1'/0/0/0 and next time in m/44'/1'/0/0/1 and next time in m/44'/1'/0/0/2
     *
     * returns the path
     *
     * @param string    $identifier             the identifier of the wallet
     * @param string    $path                   the parent path for which to get a new derivation
     * @return string
     */
    public function getNewDerivation($identifier, $path);

    /**
     * get a new derivation number for specified parent path
     *  eg; m/44'/1'/0/0 results in m/44'/1'/0/0/0 and next time in m/44'/1'/0/0/1 and next time in m/44'/1'/0/0/2
     *
     * @param string    $identifier             the identifier of the wallet
     * @param string    $path                   the parent path for which to get a new derivation
     * @return mixed
     */
    public function _getNewDerivation($identifier, $path);

    /**
     * send the transaction using the API
     *
     * @param string    $identifier             the identifier of the wallet
     * @param string    $rawTransaction         raw hex of the transaction (should be partially signed)
     * @param array     $paths                  list of the paths that were used for the UTXO
     * @param bool      $checkFee               let the server verify the fee after signing
     * @return string                           the complete raw transaction
     * @throws \Exception
     */
    public function sendTransaction($identifier, $rawTransaction, $paths, $checkFee);

    /**
     * use the API to get the best inputs to use based on the outputs
     *
     * the return array has the following format:
     * [
     *  "utxos" => [
     *      [
     *          "hash" => "<txHash>",
     *          "idx" => "<index of the output of that <txHash>",
     *          "scriptpubkey_hex" => "<scriptPubKey-hex>",
     *          "value" => 32746327,
     *          "address" => "1address",
     *          "path" => "m/44'/1'/0'/0/13",
     *          "redeem_script" => "<redeemScript-hex>",
     *      ],
     *  ],
     *  "fee"   => 10000,
     *  "change"=> 1010109201,
     * ]
     *
     * @param string   $identifier              the identifier of the wallet
     * @param array    $outputs                 the outputs you want to create - array[address => satoshi-value]
     * @param bool     $lockUTXO                when TRUE the UTXOs selected will be locked for a few seconds
     *                                          so you have some time to spend them without race-conditions
     * @param bool     $allowZeroConf
     * @param string   $feeStrategy
     * @param null|int $forceFee
     * @return array
     */
    public function coinSelection($identifier, $outputs, $lockUTXO = false, $allowZeroConf = false, $feeStrategy = Wallet::FEE_STRATEGY_OPTIMAL, $forceFee = null);

    /**
     * @return array        ['optimal_fee' => 10000, 'low_priority_fee' => 5000]
     */
    public function feePerKB();

    /**
     * get the current price index
     *
     * @return array        eg; ['USD' => 287.30]
     */
    public function price();

    /**
     * setup webhook for wallet
     *
     * @param string    $identifier         the wallet identifier for which to create the webhook
     * @param string    $webhookIdentifier  the webhook identifier to use
     * @param string    $url                the url to receive the webhook events
     * @return array
     */
    public function setupWalletWebhook($identifier, $webhookIdentifier, $url);

    /**
     * delete webhook for wallet
     *
     * @param string    $identifier         the wallet identifier for which to delete the webhook
     * @param string    $webhookIdentifier  the webhook identifier to delete
     * @return array
     */
    public function deleteWalletWebhook($identifier, $webhookIdentifier);

    /**
     * lock a specific unspent output
     *
     * @param     $identifier
     * @param     $txHash
     * @param     $txIdx
     * @param int $ttl
     * @return
     */
    public function lockWalletUTXO($identifier, $txHash, $txIdx, $ttl = 3);

    /**
     * unlock a specific unspent output
     *
     * @param     $identifier
     * @param     $txHash
     * @param     $txIdx
     * @return
     */
    public function unlockWalletUTXO($identifier, $txHash, $txIdx);

    /**
     * get all transactions for wallet (paginated)
     *
     * @param  string  $identifier  the wallet identifier for which to get transactions
     * @param  integer $page        pagination: page number
     * @param  integer $limit       pagination: records per page (max 500)
     * @param  string  $sortDir     pagination: sort direction (asc|desc)
     * @return array                associative array containing the response
     */
    public function walletTransactions($identifier, $page = 1, $limit = 20, $sortDir = 'asc');

    /**
     * get all addresses for wallet (paginated)
     *
     * @param  string  $identifier  the wallet identifier for which to get addresses
     * @param  integer $page        pagination: page number
     * @param  integer $limit       pagination: records per page (max 500)
     * @param  string  $sortDir     pagination: sort direction (asc|desc)
     * @return array                associative array containing the response
     */
    public function walletAddresses($identifier, $page = 1, $limit = 20, $sortDir = 'asc');

    /**
     * get all UTXOs for wallet (paginated)
     *
     * @param  string  $identifier  the wallet identifier for which to get addresses
     * @param  integer $page        pagination: page number
     * @param  integer $limit       pagination: records per page (max 500)
     * @param  string  $sortDir     pagination: sort direction (asc|desc)
     * @return array                associative array containing the response
     */
    public function walletUTXOs($identifier, $page = 1, $limit = 20, $sortDir = 'asc');

    /**
     * get a paginated list of all wallets associated with the api user
     *
     * @param  integer          $page    pagination: page number
     * @param  integer          $limit   pagination: records per page
     * @return array                     associative array containing the response
     */
    public function allWallets($page = 1, $limit = 20);

    /**
     * verify a message signed bitcoin-core style
     *
     * @param  string           $message
     * @param  string           $address
     * @param  string           $signature
     * @return boolean
     */
    public function verifyMessage($message, $address, $signature);

    /**
     * convert a Satoshi value to a BTC value
     *
     * @param int       $satoshi
     * @return float
     */
    public static function toBTC($satoshi);

    /**
     * convert a Satoshi value to a BTC value and return it as a string

     * @param int       $satoshi
     * @return string
     */
    public static function toBTCString($satoshi);

    /**
     * convert a BTC value to a Satoshi value
     *
     * @param float     $btc
     * @return string
     */
    public static function toSatoshi($btc);

    /**
     * sort public keys for multisig script
     *
     * @param string[] $pubKeys
     * @return string[]
     */
    public static function sortMultisigKeys(array $pubKeys);
}
