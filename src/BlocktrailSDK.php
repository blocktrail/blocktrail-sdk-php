<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Crypto\EcAdapter\EcSerializer;
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PublicKeyInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Serializer\Signature\CompactSignatureSerializerInterface;
use BitWasp\Bitcoin\Crypto\Random\Random;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKey;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\MessageSigner\MessageSigner;
use BitWasp\Bitcoin\MessageSigner\SignedMessage;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Bitcoin\Mnemonic\MnemonicFactory;
use BitWasp\Bitcoin\Network\NetworkFactory;
use BitWasp\Bitcoin\Transaction\TransactionFactory;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\BufferInterface;
use Blocktrail\CryptoJSAES\CryptoJSAES;
use Blocktrail\SDK\Address\AddressReaderBase;
use Blocktrail\SDK\Address\BitcoinAddressReader;
use Blocktrail\SDK\Address\BitcoinCashAddressReader;
use Blocktrail\SDK\Address\CashAddress;
use Blocktrail\SDK\Backend\BlocktrailConverter;
use Blocktrail\SDK\Backend\BtccomConverter;
use Blocktrail\SDK\Backend\ConverterInterface;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Connection\RestClient;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;
use Blocktrail\SDK\Network\BitcoinCash;
use Blocktrail\SDK\Connection\RestClientInterface;
use Blocktrail\SDK\Network\BitcoinCashRegtest;
use Blocktrail\SDK\Network\BitcoinCashTestnet;
use Btccom\JustEncrypt\Encryption;
use Btccom\JustEncrypt\EncryptionMnemonic;
use Btccom\JustEncrypt\KeyDerivation;

/**
 * Class BlocktrailSDK
 */
class BlocktrailSDK implements BlocktrailSDKInterface {
    /**
     * @var Connection\RestClientInterface
     */
    protected $blocktrailClient;

    /**
     * @var Connection\RestClient
     */
    protected $dataClient;

    /**
     * @var string          currently only supporting; bitcoin
     */
    protected $network;

    /**
     * @var bool
     */
    protected $testnet;

    /**
     * @var ConverterInterface
     */
    protected $converter;

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

        list ($apiNetwork, $testnet) = Util::parseApiNetwork($network, $testnet);

        if (is_null($apiEndpoint)) {
            $apiEndpoint = getenv('BLOCKTRAIL_SDK_API_ENDPOINT') ?: "https://wallet-api.btc.com";
            $apiEndpoint = "{$apiEndpoint}/{$apiVersion}/{$apiNetwork}/";
        }

        // normalize network and set bitcoinlib to the right magic-bytes
        list($this->network, $this->testnet, $regtest) = $this->normalizeNetwork($network, $testnet);
        $this->setBitcoinLibMagicBytes($this->network, $this->testnet, $regtest);

        $btccomEndpoint = getenv('BLOCKTRAIL_SDK_BTCCOM_API_ENDPOINT');
        if (!$btccomEndpoint) {
            $btccomEndpoint = "https://" . ($this->network === "bitcoincash" ? "bch-chain" : "chain") . ".api.btc.com";
        }
        $btccomEndpoint = "{$btccomEndpoint}/v3/";

        if ($this->testnet && strpos($btccomEndpoint, "tchain") === false) {
            $btccomEndpoint = \str_replace("chain", "tchain", $btccomEndpoint);
        }

        $this->blocktrailClient = new RestClient($apiEndpoint, $apiVersion, $apiKey, $apiSecret);
        $this->dataClient = new RestClient($btccomEndpoint, $apiVersion, $apiKey, $apiSecret);
        $this->converter = new BtccomConverter();
    }

    /**
     * normalize network string
     *
     * @param $network
     * @param $testnet
     * @return array
     * @throws \Exception
     */
    protected function normalizeNetwork($network, $testnet) {
        // [name, testnet, network]
        return Util::normalizeNetwork($network, $testnet);
    }

    /**
     * set BitcoinLib to the correct magic-byte defaults for the selected network
     *
     * @param $network
     * @param bool $testnet
     * @param bool $regtest
     */
    protected function setBitcoinLibMagicBytes($network, $testnet, $regtest) {

        if ($network === "bitcoin") {
            if ($regtest) {
                $useNetwork = NetworkFactory::bitcoinRegtest();
            } else if ($testnet) {
                $useNetwork = NetworkFactory::bitcoinTestnet();
            } else {
                $useNetwork = NetworkFactory::bitcoin();
            }
        } else if ($network === "bitcoincash") {
            if ($regtest) {
                $useNetwork = new BitcoinCashRegtest();
            } else if ($testnet) {
                $useNetwork = new BitcoinCashTestnet();
            } else {
                $useNetwork = new BitcoinCash();
            }
        }

        Bitcoin::setNetwork($useNetwork);
    }

    /**
     * enable CURL debugging output
     *
     * @param   bool        $debug
     *
     * @codeCoverageIgnore
     */
    public function setCurlDebugging($debug = true) {
        $this->blocktrailClient->setCurlDebugging($debug);
        $this->dataClient->setCurlDebugging($debug);
    }

    /**
     * enable verbose errors
     *
     * @param   bool        $verboseErrors
     *
     * @codeCoverageIgnore
     */
    public function setVerboseErrors($verboseErrors = true) {
        $this->blocktrailClient->setVerboseErrors($verboseErrors);
        $this->dataClient->setVerboseErrors($verboseErrors);
    }
    
    /**
     * set cURL default option on Guzzle client
     * @param string    $key
     * @param bool      $value
     *
     * @codeCoverageIgnore
     */
    public function setCurlDefaultOption($key, $value) {
        $this->blocktrailClient->setCurlDefaultOption($key, $value);
        $this->dataClient->setCurlDefaultOption($key, $value);
    }

    /**
     * @return  RestClientInterface
     */
    public function getRestClient() {
        return $this->blocktrailClient;
    }

    /**
     * @return  RestClient
     */
    public function getDataRestClient() {
        return $this->dataClient;
    }

    /**
     * @param RestClientInterface $restClient
     */
    public function setRestClient(RestClientInterface $restClient) {
        $this->blocktrailClient = $restClient;
    }

    /**
     * get a single address
     * @param  string $address address hash
     * @return array           associative array containing the response
     */
    public function address($address) {
        $response = $this->dataClient->get($this->converter->getUrlForAddress($address));
        return $this->converter->convertAddress($response->body());
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
        $queryString = [
            'page' => $page,
            'limit' => $limit,
            'sort_dir' => $sortDir,
        ];
        $response = $this->dataClient->get($this->converter->getUrlForAddressTransactions($address), $this->converter->paginationParams($queryString));
        return $this->converter->convertAddressTxs($response->body());
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
        $queryString = [
            'page' => $page,
            'limit' => $limit,
            'sort_dir' => $sortDir
        ];
        $response = $this->dataClient->get($this->converter->getUrlForAddressTransactions($address), $this->converter->paginationParams($queryString));
        return $this->converter->convertAddressTxs($response->body());
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
        $queryString = [
            'page' => $page,
            'limit' => $limit,
            'sort_dir' => $sortDir
        ];
        $response = $this->dataClient->get($this->converter->getUrlForAddressUnspent($address), $this->converter->paginationParams($queryString));
        return $this->converter->convertAddressUnspentOutputs($response->body(), $address);
    }

    /**
     * get all unspent outputs for a batch of addresses (paginated)
     *
     * @param  string[] $addresses
     * @param  integer  $page    pagination: page number
     * @param  integer  $limit   pagination: records per page (max 500)
     * @param  string   $sortDir pagination: sort direction (asc|desc)
     * @return array associative array containing the response
     * @throws \Exception
     */
    public function batchAddressUnspentOutputs($addresses, $page = 1, $limit = 20, $sortDir = 'asc') {
        $queryString = [
            'page' => $page,
            'limit' => $limit,
            'sort_dir' => $sortDir
        ];

        if ($this->converter instanceof BtccomConverter) {
            if ($page > 1) {
                return [
                    'data' => [],
                    'current_page' => 2,
                    'per_page' => null,
                    'total' => null,
                ];
            }

            $response = $this->dataClient->get($this->converter->getUrlForBatchAddressesUnspent($addresses), $this->converter->paginationParams($queryString));
            return $this->converter->convertBatchAddressesUnspentOutputs($response->body());
        } else {
            $response = $this->client->post("address/unspent-outputs", $queryString, ['addresses' => $addresses]);
            return self::jsonDecode($response->body(), true);
        }
    }

    /**
     * verify ownership of an address
     * @param  string  $address     address hash
     * @param  string  $signature   a signed message (the address hash) using the private key of the address
     * @return array                associative array containing the response
     */
    public function verifyAddress($address, $signature) {
        if ($this->verifyMessage($address, $address, $signature)) {
            return ['result' => true, 'msg' => 'Successfully verified'];
        } else {
            return ['result' => false];
        }
    }

    /**
     * get all blocks (paginated)
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function allBlocks($page = 1, $limit = 20, $sortDir = 'asc') {
        $queryString = [
            'page' => $page,
            'limit' => $limit,
            'sort_dir' => $sortDir
        ];
        $response = $this->dataClient->get($this->converter->getUrlForAllBlocks(), $this->converter->paginationParams($queryString));
        return $this->converter->convertBlocks($response->body());
    }

    /**
     * get the latest block
     * @return array            associative array containing the response
     */
    public function blockLatest() {
        $response = $this->dataClient->get($this->converter->getUrlForBlock("latest"));
        return $this->converter->convertBlock($response->body());
    }

    /**
     * get the wallet API's latest block ['hash' => x, 'height' => y]
     * @return array            associative array containing the response
     */
    public function getWalletBlockLatest() {
        $response = $this->blocktrailClient->get("block/latest");
        return BlocktrailSDK::jsonDecode($response->body(), true);
    }

    /**
     * get an individual block
     * @param  string|integer $block    a block hash or a block height
     * @return array                    associative array containing the response
     */
    public function block($block) {
        $response = $this->dataClient->get($this->converter->getUrlForBlock($block));
        return $this->converter->convertBlock($response->body());
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
        $queryString = [
            'page' => $page,
            'limit' => $limit,
            'sort_dir' => $sortDir
        ];
        $response = $this->dataClient->get($this->converter->getUrlForBlockTransaction($block), $this->converter->paginationParams($queryString));
        return $this->converter->convertBlockTxs($response->body());
    }

    /**
     * get a single transaction
     * @param  string $txhash transaction hash
     * @return array          associative array containing the response
     */
    public function transaction($txhash) {
        $response = $this->dataClient->get($this->converter->getUrlForTransaction($txhash));
        $res = $this->converter->convertTx($response->body(), null);

        if ($this->converter instanceof BtccomConverter) {
            $res['raw'] = \json_decode($this->dataClient->get("tx/{$txhash}/raw")->body(), true)['data'];
        }

        return $res;
    }

    /**
     * get a single transaction
     * @param  string[] $txhashes list of transaction hashes (up to 20)
     * @return array[]            array containing the response
     */
    public function transactions($txhashes) {
        $response = $this->dataClient->get($this->converter->getUrlForTransactions($txhashes));
        return $this->converter->convertTxs($response->body());
    }
    
    /**
     * get a paginated list of all webhooks associated with the api user
     * @param  integer          $page    pagination: page number
     * @param  integer          $limit   pagination: records per page
     * @return array                     associative array containing the response
     */
    public function allWebhooks($page = 1, $limit = 20) {
        $queryString = [
            'page' => $page,
            'limit' => $limit
        ];
        $response = $this->blocktrailClient->get("webhooks", $this->converter->paginationParams($queryString));
        return self::jsonDecode($response->body(), true);
    }

    /**
     * get an existing webhook by it's identifier
     * @param string    $identifier     a unique identifier associated with the webhook
     * @return array                    associative array containing the response
     */
    public function getWebhook($identifier) {
        $response = $this->blocktrailClient->get("webhook/".$identifier);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * create a new webhook
     * @param  string  $url        the url to receive the webhook events
     * @param  string  $identifier a unique identifier to associate with this webhook
     * @return array               associative array containing the response
     */
    public function setupWebhook($url, $identifier = null) {
        $postData = [
            'url'        => $url,
            'identifier' => $identifier
        ];
        $response = $this->blocktrailClient->post("webhook", null, $postData, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * update an existing webhook
     * @param  string  $identifier      the unique identifier of the webhook to update
     * @param  string  $newUrl          the new url to receive the webhook events
     * @param  string  $newIdentifier   a new unique identifier to associate with this webhook
     * @return array                    associative array containing the response
     */
    public function updateWebhook($identifier, $newUrl = null, $newIdentifier = null) {
        $putData = [
            'url'        => $newUrl,
            'identifier' => $newIdentifier
        ];
        $response = $this->blocktrailClient->put("webhook/{$identifier}", null, $putData, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * deletes an existing webhook and any event subscriptions associated with it
     * @param  string  $identifier      the unique identifier of the webhook to delete
     * @return boolean                  true on success
     */
    public function deleteWebhook($identifier) {
        $response = $this->blocktrailClient->delete("webhook/{$identifier}", null, null, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * get a paginated list of all the events a webhook is subscribed to
     * @param  string  $identifier  the unique identifier of the webhook
     * @param  integer $page        pagination: page number
     * @param  integer $limit       pagination: records per page
     * @return array                associative array containing the response
     */
    public function getWebhookEvents($identifier, $page = 1, $limit = 20) {
        $queryString = [
            'page' => $page,
            'limit' => $limit
        ];
        $response = $this->blocktrailClient->get("webhook/{$identifier}/events", $this->converter->paginationParams($queryString));
        return self::jsonDecode($response->body(), true);
    }
    
    /**
     * subscribes a webhook to transaction events of one particular transaction
     * @param  string  $identifier      the unique identifier of the webhook to be triggered
     * @param  string  $transaction     the transaction hash
     * @param  integer $confirmations   the amount of confirmations to send.
     * @return array                    associative array containing the response
     */
    public function subscribeTransaction($identifier, $transaction, $confirmations = 6) {
        $postData = [
            'event_type'    => 'transaction',
            'transaction'   => $transaction,
            'confirmations' => $confirmations,
        ];
        $response = $this->blocktrailClient->post("webhook/{$identifier}/events", null, $postData, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * subscribes a webhook to transaction events on a particular address
     * @param  string  $identifier      the unique identifier of the webhook to be triggered
     * @param  string  $address         the address hash
     * @param  integer $confirmations   the amount of confirmations to send.
     * @return array                    associative array containing the response
     */
    public function subscribeAddressTransactions($identifier, $address, $confirmations = 6) {
        $postData = [
            'event_type'    => 'address-transactions',
            'address'       => $address,
            'confirmations' => $confirmations,
        ];
        $response = $this->blocktrailClient->post("webhook/{$identifier}/events", null, $postData, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

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
    public function batchSubscribeAddressTransactions($identifier, $batchData) {
        $postData = [];
        foreach ($batchData as $record) {
            $postData[] = [
                'event_type' => 'address-transactions',
                'address' => $record['address'],
                'confirmations' => isset($record['confirmations']) ? $record['confirmations'] : 6,
            ];
        }
        $response = $this->blocktrailClient->post("webhook/{$identifier}/events/batch", null, $postData, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * subscribes a webhook to a new block event
     * @param  string  $identifier  the unique identifier of the webhook to be triggered
     * @return array                associative array containing the response
     */
    public function subscribeNewBlocks($identifier) {
        $postData = [
            'event_type'    => 'block',
        ];
        $response = $this->blocktrailClient->post("webhook/{$identifier}/events", null, $postData, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * removes an transaction event subscription from a webhook
     * @param  string  $identifier      the unique identifier of the webhook associated with the event subscription
     * @param  string  $transaction     the transaction hash of the event subscription
     * @return boolean                  true on success
     */
    public function unsubscribeTransaction($identifier, $transaction) {
        $response = $this->blocktrailClient->delete("webhook/{$identifier}/transaction/{$transaction}", null, null, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * removes an address transaction event subscription from a webhook
     * @param  string  $identifier      the unique identifier of the webhook associated with the event subscription
     * @param  string  $address         the address hash of the event subscription
     * @return boolean                  true on success
     */
    public function unsubscribeAddressTransactions($identifier, $address) {
        $response = $this->blocktrailClient->delete("webhook/{$identifier}/address-transactions/{$address}", null, null, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * removes a block event subscription from a webhook
     * @param  string  $identifier      the unique identifier of the webhook associated with the event subscription
     * @return boolean                  true on success
     */
    public function unsubscribeNewBlocks($identifier) {
        $response = $this->blocktrailClient->delete("webhook/{$identifier}/block", null, null, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

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
     * (@nonPHP-doc) @param int  $keyIndex          override for the blocktrail cosigning key to use
     *
     * @return array[WalletInterface, array]      list($wallet, $backupInfo)
     * @throws \Exception
     */
    public function createNewWallet($options) {
        if (!is_array($options)) {
            $args = func_get_args();
            $options = [
                "identifier" => $args[0],
                "password" => $args[1],
                "key_index" => isset($args[2]) ? $args[2] : null,
            ];
        }

        if (isset($options['password'])) {
            if (isset($options['passphrase'])) {
                throw new \InvalidArgumentException("Can only provide either passphrase or password");
            } else {
                $options['passphrase'] = $options['password'];
            }
        }

        if (!isset($options['passphrase'])) {
            $options['passphrase'] = null;
        }

        if (!isset($options['key_index'])) {
            $options['key_index'] = 0;
        }

        if (!isset($options['wallet_version'])) {
            $options['wallet_version'] = Wallet::WALLET_VERSION_V3;
        }

        switch ($options['wallet_version']) {
            case Wallet::WALLET_VERSION_V1:
                return $this->createNewWalletV1($options);

            case Wallet::WALLET_VERSION_V2:
                return $this->createNewWalletV2($options);

            case Wallet::WALLET_VERSION_V3:
                return $this->createNewWalletV3($options);

            default:
                throw new \InvalidArgumentException("Invalid wallet version");
        }
    }

    protected function createNewWalletV1($options) {
        $walletPath = WalletPath::create($options['key_index']);

        $storePrimaryMnemonic = isset($options['store_primary_mnemonic']) ? $options['store_primary_mnemonic'] : null;

        if (isset($options['primary_mnemonic']) && isset($options['primary_private_key'])) {
            throw new \InvalidArgumentException("Can't specify Primary Mnemonic and Primary PrivateKey");
        }

        $primaryMnemonic = null;
        $primaryPrivateKey = null;
        if (!isset($options['primary_mnemonic']) && !isset($options['primary_private_key'])) {
            if (!$options['passphrase']) {
                throw new \InvalidArgumentException("Can't generate Primary Mnemonic without a passphrase");
            } else {
                // create new primary seed
                /** @var HierarchicalKey $primaryPrivateKey */
                list($primaryMnemonic, , $primaryPrivateKey) = $this->newV1PrimarySeed($options['passphrase']);
                if ($storePrimaryMnemonic !== false) {
                    $storePrimaryMnemonic = true;
                }
            }
        } elseif (isset($options['primary_mnemonic'])) {
            $primaryMnemonic = $options['primary_mnemonic'];
        } elseif (isset($options['primary_private_key'])) {
            $primaryPrivateKey = $options['primary_private_key'];
        }

        if ($storePrimaryMnemonic && $primaryMnemonic && !$options['passphrase']) {
            throw new \InvalidArgumentException("Can't store Primary Mnemonic on server without a passphrase");
        }

        if ($primaryPrivateKey) {
            if (is_string($primaryPrivateKey)) {
                $primaryPrivateKey = [$primaryPrivateKey, "m"];
            }
        } else {
            $primaryPrivateKey = HierarchicalKeyFactory::fromEntropy((new Bip39SeedGenerator())->getSeed($primaryMnemonic, $options['passphrase']));
        }

        if (!$storePrimaryMnemonic) {
            $primaryMnemonic = false;
        }

        // create primary public key from the created private key
        $path = $walletPath->keyIndexPath()->publicPath();
        $primaryPublicKey = BIP32Key::create($primaryPrivateKey, "m")->buildKey($path);

        if (isset($options['backup_mnemonic']) && $options['backup_public_key']) {
            throw new \InvalidArgumentException("Can't specify Backup Mnemonic and Backup PublicKey");
        }

        $backupMnemonic = null;
        $backupPublicKey = null;
        if (!isset($options['backup_mnemonic']) && !isset($options['backup_public_key'])) {
            /** @var HierarchicalKey $backupPrivateKey */
            list($backupMnemonic, , ) = $this->newV1BackupSeed();
        } else if (isset($options['backup_mnemonic'])) {
            $backupMnemonic = $options['backup_mnemonic'];
        } elseif (isset($options['backup_public_key'])) {
            $backupPublicKey = $options['backup_public_key'];
        }

        if ($backupPublicKey) {
            if (is_string($backupPublicKey)) {
                $backupPublicKey = [$backupPublicKey, "m"];
            }
        } else {
            $backupPrivateKey = HierarchicalKeyFactory::fromEntropy((new Bip39SeedGenerator())->getSeed($backupMnemonic, ""));
            $backupPublicKey = BIP32Key::create($backupPrivateKey->toPublic(), "M");
        }

        // create a checksum of our private key which we'll later use to verify we used the right password
        $checksum = $primaryPrivateKey->getPublicKey()->getAddress()->getAddress();
        $addressReader = $this->makeAddressReader($options);

        // send the public keys to the server to store them
        //  and the mnemonic, which is safe because it's useless without the password
        $data = $this->storeNewWalletV1(
            $options['identifier'],
            $primaryPublicKey->tuple(),
            $backupPublicKey->tuple(),
            $primaryMnemonic,
            $checksum,
            $options['key_index'],
            array_key_exists('segwit', $options) ? $options['segwit'] : false
        );

        // received the blocktrail public keys
        $blocktrailPublicKeys = Util::arrayMapWithIndex(function ($keyIndex, $pubKeyTuple) {
            return [$keyIndex, BIP32Key::create(HierarchicalKeyFactory::fromExtended($pubKeyTuple[0]), $pubKeyTuple[1])];
        }, $data['blocktrail_public_keys']);

        $wallet = new WalletV1(
            $this,
            $options['identifier'],
            $primaryMnemonic,
            [$options['key_index'] => $primaryPublicKey],
            $backupPublicKey,
            $blocktrailPublicKeys,
            $options['key_index'],
            $this->network,
            $this->testnet,
            array_key_exists('segwit', $data) ? $data['segwit'] : false,
            $addressReader,
            $checksum
        );

        $wallet->unlock($options);

        // return wallet and backup mnemonic
        return [
            $wallet,
            [
                'primary_mnemonic' => $primaryMnemonic,
                'backup_mnemonic' => $backupMnemonic,
                'blocktrail_public_keys' => $blocktrailPublicKeys,
            ],
        ];
    }

    public function randomBits($bits) {
        return $this->randomBytes($bits / 8);
    }

    public function randomBytes($bytes) {
        return (new Random())->bytes($bytes)->getBinary();
    }

    protected function createNewWalletV2($options) {
        $walletPath = WalletPath::create($options['key_index']);

        if (isset($options['store_primary_mnemonic'])) {
            $options['store_data_on_server'] = $options['store_primary_mnemonic'];
        }

        if (!isset($options['store_data_on_server'])) {
            if (isset($options['primary_private_key'])) {
                $options['store_data_on_server'] = false;
            } else {
                $options['store_data_on_server'] = true;
            }
        }

        $storeDataOnServer = $options['store_data_on_server'];

        $secret = null;
        $encryptedSecret = null;
        $primarySeed = null;
        $encryptedPrimarySeed = null;
        $recoverySecret = null;
        $recoveryEncryptedSecret = null;
        $backupSeed = null;

        if (!isset($options['primary_private_key'])) {
            $primarySeed = isset($options['primary_seed']) ? $options['primary_seed'] : $this->newV2PrimarySeed();
        }

        if ($storeDataOnServer) {
            if (!isset($options['secret'])) {
                if (!$options['passphrase']) {
                    throw new \InvalidArgumentException("Can't encrypt data without a passphrase");
                }

                list($secret, $encryptedSecret) = $this->newV2Secret($options['passphrase']);
            } else {
                $secret = $options['secret'];
            }

            $encryptedPrimarySeed = $this->newV2EncryptedPrimarySeed($primarySeed, $secret);
            list($recoverySecret, $recoveryEncryptedSecret) = $this->newV2RecoverySecret($secret);
        }

        if (!isset($options['backup_public_key'])) {
            $backupSeed = isset($options['backup_seed']) ? $options['backup_seed'] : $this->newV2BackupSeed();
        }

        if (isset($options['primary_private_key'])) {
            $options['primary_private_key'] = BlocktrailSDK::normalizeBIP32Key($options['primary_private_key']);
        } else {
            $options['primary_private_key'] = BIP32Key::create(HierarchicalKeyFactory::fromEntropy(new Buffer($primarySeed)), "m");
        }

        // create primary public key from the created private key
        $options['primary_public_key'] = $options['primary_private_key']->buildKey($walletPath->keyIndexPath()->publicPath());

        if (!isset($options['backup_public_key'])) {
            $options['backup_public_key'] = BIP32Key::create(HierarchicalKeyFactory::fromEntropy(new Buffer($backupSeed)), "m")->buildKey("M");
        }

        // create a checksum of our private key which we'll later use to verify we used the right password
        $checksum = $options['primary_private_key']->publicKey()->getAddress()->getAddress();
        $addressReader = $this->makeAddressReader($options);

        // send the public keys and encrypted data to server
        $data = $this->storeNewWalletV2(
            $options['identifier'],
            $options['primary_public_key']->tuple(),
            $options['backup_public_key']->tuple(),
            $storeDataOnServer ? $encryptedPrimarySeed : false,
            $storeDataOnServer ? $encryptedSecret : false,
            $storeDataOnServer ? $recoverySecret : false,
            $checksum,
            $options['key_index'],
            array_key_exists('segwit', $options) ? $options['segwit'] : false
        );

        // received the blocktrail public keys
        $blocktrailPublicKeys = Util::arrayMapWithIndex(function ($keyIndex, $pubKeyTuple) {
            return [$keyIndex, BIP32Key::create(HierarchicalKeyFactory::fromExtended($pubKeyTuple[0]), $pubKeyTuple[1])];
        }, $data['blocktrail_public_keys']);

        $wallet = new WalletV2(
            $this,
            $options['identifier'],
            $encryptedPrimarySeed,
            $encryptedSecret,
            [$options['key_index'] => $options['primary_public_key']],
            $options['backup_public_key'],
            $blocktrailPublicKeys,
            $options['key_index'],
            $this->network,
            $this->testnet,
            array_key_exists('segwit', $data) ? $data['segwit'] : false,
            $addressReader,
            $checksum
        );

        $wallet->unlock([
            'passphrase' => isset($options['passphrase']) ? $options['passphrase'] : null,
            'primary_private_key' => $options['primary_private_key'],
            'primary_seed' => $primarySeed,
            'secret' => $secret,
        ]);

        // return wallet and mnemonics for backup sheet
        return [
            $wallet,
            [
                'encrypted_primary_seed' => $encryptedPrimarySeed ? MnemonicFactory::bip39()->entropyToMnemonic(new Buffer(base64_decode($encryptedPrimarySeed))) : null,
                'backup_seed' => $backupSeed ? MnemonicFactory::bip39()->entropyToMnemonic(new Buffer($backupSeed)) : null,
                'recovery_encrypted_secret' => $recoveryEncryptedSecret ? MnemonicFactory::bip39()->entropyToMnemonic(new Buffer(base64_decode($recoveryEncryptedSecret))) : null,
                'encrypted_secret' => $encryptedSecret ? MnemonicFactory::bip39()->entropyToMnemonic(new Buffer(base64_decode($encryptedSecret))) : null,
                'blocktrail_public_keys' => Util::arrayMapWithIndex(function ($keyIndex, BIP32Key $pubKey) {
                    return [$keyIndex, $pubKey->tuple()];
                }, $blocktrailPublicKeys),
            ],
        ];
    }

    protected function createNewWalletV3($options) {
        $walletPath = WalletPath::create($options['key_index']);

        if (isset($options['store_primary_mnemonic'])) {
            $options['store_data_on_server'] = $options['store_primary_mnemonic'];
        }

        if (!isset($options['store_data_on_server'])) {
            if (isset($options['primary_private_key'])) {
                $options['store_data_on_server'] = false;
            } else {
                $options['store_data_on_server'] = true;
            }
        }

        $storeDataOnServer = $options['store_data_on_server'];

        $secret = null;
        $encryptedSecret = null;
        $primarySeed = null;
        $encryptedPrimarySeed = null;
        $recoverySecret = null;
        $recoveryEncryptedSecret = null;
        $backupSeed = null;

        if (!isset($options['primary_private_key'])) {
            if (isset($options['primary_seed'])) {
                if (!$options['primary_seed'] instanceof BufferInterface) {
                    throw new \InvalidArgumentException('Primary Seed should be passed as a Buffer');
                }
                $primarySeed = $options['primary_seed'];
            } else {
                $primarySeed = $this->newV3PrimarySeed();
            }
        }

        if ($storeDataOnServer) {
            if (!isset($options['secret'])) {
                if (!$options['passphrase']) {
                    throw new \InvalidArgumentException("Can't encrypt data without a passphrase");
                }

                list($secret, $encryptedSecret) = $this->newV3Secret($options['passphrase']);
            } else {
                if (!$options['secret'] instanceof Buffer) {
                    throw new \InvalidArgumentException('Secret must be provided as a Buffer');
                }

                $secret = $options['secret'];
            }

            $encryptedPrimarySeed = $this->newV3EncryptedPrimarySeed($primarySeed, $secret);
            list($recoverySecret, $recoveryEncryptedSecret) = $this->newV3RecoverySecret($secret);
        }

        if (!isset($options['backup_public_key'])) {
            if (isset($options['backup_seed'])) {
                if (!$options['backup_seed'] instanceof Buffer) {
                    throw new \InvalidArgumentException('Backup seed must be an instance of Buffer');
                }
                $backupSeed = $options['backup_seed'];
            } else {
                $backupSeed = $this->newV3BackupSeed();
            }
        }

        if (isset($options['primary_private_key'])) {
            $options['primary_private_key'] = BlocktrailSDK::normalizeBIP32Key($options['primary_private_key']);
        } else {
            $options['primary_private_key'] = BIP32Key::create(HierarchicalKeyFactory::fromEntropy($primarySeed), "m");
        }

        // create primary public key from the created private key
        $options['primary_public_key'] = $options['primary_private_key']->buildKey($walletPath->keyIndexPath()->publicPath());

        if (!isset($options['backup_public_key'])) {
            $options['backup_public_key'] = BIP32Key::create(HierarchicalKeyFactory::fromEntropy($backupSeed), "m")->buildKey("M");
        }

        // create a checksum of our private key which we'll later use to verify we used the right password
        $checksum = $options['primary_private_key']->publicKey()->getAddress()->getAddress();
        $addressReader = $this->makeAddressReader($options);

        // send the public keys and encrypted data to server
        $data = $this->storeNewWalletV3(
            $options['identifier'],
            $options['primary_public_key']->tuple(),
            $options['backup_public_key']->tuple(),
            $storeDataOnServer ? base64_encode($encryptedPrimarySeed->getBinary()) : false,
            $storeDataOnServer ? base64_encode($encryptedSecret->getBinary()) : false,
            $storeDataOnServer ? $recoverySecret->getHex() : false,
            $checksum,
            $options['key_index'],
            array_key_exists('segwit', $options) ? $options['segwit'] : false
        );

        // received the blocktrail public keys
        $blocktrailPublicKeys = Util::arrayMapWithIndex(function ($keyIndex, $pubKeyTuple) {
            return [$keyIndex, BIP32Key::create(HierarchicalKeyFactory::fromExtended($pubKeyTuple[0]), $pubKeyTuple[1])];
        }, $data['blocktrail_public_keys']);

        $wallet = new WalletV3(
            $this,
            $options['identifier'],
            $encryptedPrimarySeed,
            $encryptedSecret,
            [$options['key_index'] => $options['primary_public_key']],
            $options['backup_public_key'],
            $blocktrailPublicKeys,
            $options['key_index'],
            $this->network,
            $this->testnet,
            array_key_exists('segwit', $data) ? $data['segwit'] : false,
            $addressReader,
            $checksum
        );

        $wallet->unlock([
            'passphrase' => isset($options['passphrase']) ? $options['passphrase'] : null,
            'primary_private_key' => $options['primary_private_key'],
            'primary_seed' => $primarySeed,
            'secret' => $secret,
        ]);

        // return wallet and mnemonics for backup sheet
        return [
            $wallet,
            [
                'encrypted_primary_seed'    => $encryptedPrimarySeed ? EncryptionMnemonic::encode($encryptedPrimarySeed) : null,
                'backup_seed'               => $backupSeed ? MnemonicFactory::bip39()->entropyToMnemonic($backupSeed) : null,
                'recovery_encrypted_secret' => $recoveryEncryptedSecret ? EncryptionMnemonic::encode($recoveryEncryptedSecret) : null,
                'encrypted_secret'          => $encryptedSecret ? EncryptionMnemonic::encode($encryptedSecret) : null,
                'blocktrail_public_keys'    => Util::arrayMapWithIndex(function ($keyIndex, BIP32Key $pubKey) {
                    return [$keyIndex, $pubKey->tuple()];
                }, $blocktrailPublicKeys),
            ]
        ];
    }

    public function newV2PrimarySeed() {
        return $this->randomBits(256);
    }

    public function newV2BackupSeed() {
        return $this->randomBits(256);
    }

    public function newV2Secret($passphrase) {
        $secret = bin2hex($this->randomBits(256)); // string because we use it as passphrase
        $encryptedSecret = CryptoJSAES::encrypt($secret, $passphrase);

        return [$secret, $encryptedSecret];
    }

    public function newV2EncryptedPrimarySeed($primarySeed, $secret) {
        return CryptoJSAES::encrypt(base64_encode($primarySeed), $secret);
    }

    public function newV2RecoverySecret($secret) {
        $recoverySecret = bin2hex($this->randomBits(256));
        $recoveryEncryptedSecret = CryptoJSAES::encrypt($secret, $recoverySecret);

        return [$recoverySecret, $recoveryEncryptedSecret];
    }

    public function newV3PrimarySeed() {
        return new Buffer($this->randomBits(256));
    }

    public function newV3BackupSeed() {
        return new Buffer($this->randomBits(256));
    }

    public function newV3Secret($passphrase) {
        $secret = new Buffer($this->randomBits(256));
        $encryptedSecret = Encryption::encrypt($secret, new Buffer($passphrase), KeyDerivation::DEFAULT_ITERATIONS)
            ->getBuffer();

        return [$secret, $encryptedSecret];
    }

    public function newV3EncryptedPrimarySeed(Buffer $primarySeed, Buffer $secret) {
        return Encryption::encrypt($primarySeed, $secret, KeyDerivation::SUBKEY_ITERATIONS)
            ->getBuffer();
    }

    public function newV3RecoverySecret(Buffer $secret) {
        $recoverySecret = new Buffer($this->randomBits(256));
        $recoveryEncryptedSecret = Encryption::encrypt($secret, $recoverySecret, KeyDerivation::DEFAULT_ITERATIONS)
            ->getBuffer();

        return [$recoverySecret, $recoveryEncryptedSecret];
    }

    /**
     * @param array $bip32Key
     * @throws BlocktrailSDKException
     */
    private function verifyPublicBIP32Key(array $bip32Key) {
        $hk = HierarchicalKeyFactory::fromExtended($bip32Key[0]);
        if ($hk->isPrivate()) {
            throw new BlocktrailSDKException('Private key was included in request, abort');
        }

        if (substr($bip32Key[1], 0, 1) === "m") {
            throw new BlocktrailSDKException("Private path was included in the request, abort");
        }
    }

    /**
     * @param array $walletData
     * @throws BlocktrailSDKException
     */
    private function verifyPublicOnly(array $walletData) {
        $this->verifyPublicBIP32Key($walletData['primary_public_key']);
        $this->verifyPublicBIP32Key($walletData['backup_public_key']);
    }

    /**
     * create wallet using the API
     *
     * @param string    $identifier             the wallet identifier to create
     * @param array     $primaryPublicKey       BIP32 extended public key - [key, path]
     * @param array     $backupPublicKey        BIP32 extended public key - [backup key, path "M"]
     * @param string    $primaryMnemonic        mnemonic to store
     * @param string    $checksum               checksum to store
     * @param int       $keyIndex               account that we expect to use
     * @param bool      $segwit                 opt in to segwit
     * @return mixed
     */
    public function storeNewWalletV1($identifier, $primaryPublicKey, $backupPublicKey, $primaryMnemonic, $checksum, $keyIndex, $segwit = false) {
        $data = [
            'identifier' => $identifier,
            'primary_public_key' => $primaryPublicKey,
            'backup_public_key' => $backupPublicKey,
            'primary_mnemonic' => $primaryMnemonic,
            'checksum' => $checksum,
            'key_index' => $keyIndex,
            'segwit' => $segwit,
        ];
        $this->verifyPublicOnly($data);
        $response = $this->blocktrailClient->post("wallet", null, $data, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * create wallet using the API
     *
     * @param string $identifier       the wallet identifier to create
     * @param array  $primaryPublicKey BIP32 extended public key - [key, path]
     * @param array  $backupPublicKey  BIP32 extended public key - [backup key, path "M"]
     * @param        $encryptedPrimarySeed
     * @param        $encryptedSecret
     * @param        $recoverySecret
     * @param string $checksum         checksum to store
     * @param int    $keyIndex         account that we expect to use
     * @param bool   $segwit           opt in to segwit
     * @return mixed
     * @throws \Exception
     */
    public function storeNewWalletV2($identifier, $primaryPublicKey, $backupPublicKey, $encryptedPrimarySeed, $encryptedSecret, $recoverySecret, $checksum, $keyIndex, $segwit = false) {
        $data = [
            'identifier' => $identifier,
            'wallet_version' => Wallet::WALLET_VERSION_V2,
            'primary_public_key' => $primaryPublicKey,
            'backup_public_key' => $backupPublicKey,
            'encrypted_primary_seed' => $encryptedPrimarySeed,
            'encrypted_secret' => $encryptedSecret,
            'recovery_secret' => $recoverySecret,
            'checksum' => $checksum,
            'key_index' => $keyIndex,
            'segwit' => $segwit,
        ];
        $this->verifyPublicOnly($data);
        $response = $this->blocktrailClient->post("wallet", null, $data, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * create wallet using the API
     *
     * @param string $identifier       the wallet identifier to create
     * @param array  $primaryPublicKey BIP32 extended public key - [key, path]
     * @param array  $backupPublicKey  BIP32 extended public key - [backup key, path "M"]
     * @param        $encryptedPrimarySeed
     * @param        $encryptedSecret
     * @param        $recoverySecret
     * @param string $checksum         checksum to store
     * @param int    $keyIndex         account that we expect to use
     * @param bool   $segwit           opt in to segwit
     * @return mixed
     * @throws \Exception
     */
    public function storeNewWalletV3($identifier, $primaryPublicKey, $backupPublicKey, $encryptedPrimarySeed, $encryptedSecret, $recoverySecret, $checksum, $keyIndex, $segwit = false) {

        $data = [
            'identifier' => $identifier,
            'wallet_version' => Wallet::WALLET_VERSION_V3,
            'primary_public_key' => $primaryPublicKey,
            'backup_public_key' => $backupPublicKey,
            'encrypted_primary_seed' => $encryptedPrimarySeed,
            'encrypted_secret' => $encryptedSecret,
            'recovery_secret' => $recoverySecret,
            'checksum' => $checksum,
            'key_index' => $keyIndex,
            'segwit' => $segwit,
        ];

        $this->verifyPublicOnly($data);
        $response = $this->blocktrailClient->post("wallet", null, $data, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * upgrade wallet to use a new account number
     *  the account number specifies which blocktrail cosigning key is used
     *
     * @param string    $identifier             the wallet identifier to be upgraded
     * @param int       $keyIndex               the new account to use
     * @param array     $primaryPublicKey       BIP32 extended public key - [key, path]
     * @return mixed
     */
    public function upgradeKeyIndex($identifier, $keyIndex, $primaryPublicKey) {
        $data = [
            'key_index' => $keyIndex,
            'primary_public_key' => $primaryPublicKey
        ];

        $response = $this->blocktrailClient->post("wallet/{$identifier}/upgrade", null, $data, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * @param array $options
     * @return AddressReaderBase
     */
    private function makeAddressReader(array $options) {
        if ($this->network == "bitcoincash") {
            $useCashAddress = false;
            if (array_key_exists("use_cashaddress", $options) && $options['use_cashaddress']) {
                $useCashAddress = true;
            }
            return new BitcoinCashAddressReader($useCashAddress);
        } else {
            return new BitcoinAddressReader();
        }
    }

    /**
     * initialize a previously created wallet
     *
     * Takes an options object, or accepts identifier/password for backwards compatiblity.
     *
     * Some of the options:
     *  - "readonly/readOnly/read-only" can be to a boolean value,
     *    so the wallet is loaded in read-only mode (no private key)
     *  - "check_backup_key" can be set to your own backup key:
     *    Format: ["M', "xpub..."]
     *    Setting this will allow the SDK to check the server hasn't
     *    a different key (one it happens to control)

     * Either takes one argument:
     * @param array $options
     *
     * Or takes two arguments (old, deprecated syntax):
     * (@nonPHP-doc) @param string    $identifier             the wallet identifier to be initialized
     * (@nonPHP-doc) @param string    $password               the password to decrypt the mnemonic with
     *
     * @return WalletInterface
     * @throws \Exception
     */
    public function initWallet($options) {
        if (!is_array($options)) {
            $args = func_get_args();
            $options = [
                "identifier" => $args[0],
                "password" => $args[1],
            ];
        }

        $identifier = $options['identifier'];
        $readonly = isset($options['readonly']) ? $options['readonly'] :
                    (isset($options['readOnly']) ? $options['readOnly'] :
                        (isset($options['read-only']) ? $options['read-only'] :
                            false));

        // get the wallet data from the server
        $data = $this->getWallet($identifier);
        if (!$data) {
            throw new \Exception("Failed to get wallet");
        }

        if (array_key_exists('check_backup_key', $options)) {
            if (!is_string($options['check_backup_key'])) {
                throw new \InvalidArgumentException("check_backup_key should be a string (the xpub)");
            }
            if ($options['check_backup_key'] !== $data['backup_public_key'][0]) {
                throw new \InvalidArgumentException("Backup key returned from server didn't match our own");
            }
        }

        $addressReader = $this->makeAddressReader($options);

        switch ($data['wallet_version']) {
            case Wallet::WALLET_VERSION_V1:
                $wallet = new WalletV1(
                    $this,
                    $identifier,
                    isset($options['primary_mnemonic']) ? $options['primary_mnemonic'] : $data['primary_mnemonic'],
                    $data['primary_public_keys'],
                    $data['backup_public_key'],
                    $data['blocktrail_public_keys'],
                    isset($options['key_index']) ? $options['key_index'] : $data['key_index'],
                    $this->network,
                    $this->testnet,
                    array_key_exists('segwit', $data) ? $data['segwit'] : false,
                    $addressReader,
                    $data['checksum']
                );
                break;
            case Wallet::WALLET_VERSION_V2:
                $wallet = new WalletV2(
                    $this,
                    $identifier,
                    isset($options['encrypted_primary_seed']) ? $options['encrypted_primary_seed'] : $data['encrypted_primary_seed'],
                    isset($options['encrypted_secret']) ? $options['encrypted_secret'] : $data['encrypted_secret'],
                    $data['primary_public_keys'],
                    $data['backup_public_key'],
                    $data['blocktrail_public_keys'],
                    isset($options['key_index']) ? $options['key_index'] : $data['key_index'],
                    $this->network,
                    $this->testnet,
                    array_key_exists('segwit', $data) ? $data['segwit'] : false,
                    $addressReader,
                    $data['checksum']
                );
                break;
            case Wallet::WALLET_VERSION_V3:
                if (isset($options['encrypted_primary_seed'])) {
                    if (!$options['encrypted_primary_seed'] instanceof Buffer) {
                        throw new \InvalidArgumentException('Encrypted PrimarySeed must be provided as a Buffer');
                    }
                    $encryptedPrimarySeed = $data['encrypted_primary_seed'];
                } else {
                    $encryptedPrimarySeed = new Buffer(base64_decode($data['encrypted_primary_seed']));
                }

                if (isset($options['encrypted_secret'])) {
                    if (!$options['encrypted_secret'] instanceof Buffer) {
                        throw new \InvalidArgumentException('Encrypted secret must be provided as a Buffer');
                    }

                    $encryptedSecret = $data['encrypted_secret'];
                } else {
                    $encryptedSecret = new Buffer(base64_decode($data['encrypted_secret']));
                }

                $wallet = new WalletV3(
                    $this,
                    $identifier,
                    $encryptedPrimarySeed,
                    $encryptedSecret,
                    $data['primary_public_keys'],
                    $data['backup_public_key'],
                    $data['blocktrail_public_keys'],
                    isset($options['key_index']) ? $options['key_index'] : $data['key_index'],
                    $this->network,
                    $this->testnet,
                    array_key_exists('segwit', $data) ? $data['segwit'] : false,
                    $addressReader,
                    $data['checksum']
                );
                break;
            default:
                throw new \InvalidArgumentException("Invalid wallet version");
        }

        if (!$readonly) {
            $wallet->unlock($options);
        }

        return $wallet;
    }

    /**
     * get the wallet data from the server
     *
     * @param string    $identifier             the identifier of the wallet
     * @return mixed
     */
    public function getWallet($identifier) {
        $response = $this->blocktrailClient->get("wallet/{$identifier}", null, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * update the wallet data on the server
     *
     * @param string    $identifier
     * @param $data
     * @return mixed
     */
    public function updateWallet($identifier, $data) {
        $response = $this->blocktrailClient->post("wallet/{$identifier}", null, $data, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

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
    public function deleteWallet($identifier, $checksumAddress, $signature, $force = false) {
        $response = $this->blocktrailClient->delete("wallet/{$identifier}", ['force' => $force], [
            'checksum' => $checksumAddress,
            'signature' => $signature
        ], RestClient::AUTH_HTTP_SIG, 360);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * create new backup key;
     *  1) a BIP39 mnemonic
     *  2) a seed from that mnemonic with a blank password
     *  3) a private key from that seed
     *
     * @return array [mnemonic, seed, key]
     */
    protected function newV1BackupSeed() {
        list($backupMnemonic, $backupSeed, $backupPrivateKey) = $this->generateNewSeed("");

        return [$backupMnemonic, $backupSeed, $backupPrivateKey];
    }

    /**
     * create new primary key;
     *  1) a BIP39 mnemonic
     *  2) a seed from that mnemonic with the password
     *  3) a private key from that seed
     *
     * @param string    $passphrase             the password to use in the BIP39 creation of the seed
     * @return array [mnemonic, seed, key]
     * @TODO: require a strong password?
     */
    protected function newV1PrimarySeed($passphrase) {
        list($primaryMnemonic, $primarySeed, $primaryPrivateKey) = $this->generateNewSeed($passphrase);

        return [$primaryMnemonic, $primarySeed, $primaryPrivateKey];
    }

    /**
     * create a new key;
     *  1) a BIP39 mnemonic
     *  2) a seed from that mnemonic with the password
     *  3) a private key from that seed
     *
     * @param string    $passphrase             the password to use in the BIP39 creation of the seed
     * @param string    $forceEntropy           forced entropy instead of random entropy for testing purposes
     * @return array
     */
    protected function generateNewSeed($passphrase = "", $forceEntropy = null) {
        // generate master seed, retry if the generated private key isn't valid (FALSE is returned)
        do {
            $mnemonic = $this->generateNewMnemonic($forceEntropy);

            $seed = (new Bip39SeedGenerator)->getSeed($mnemonic, $passphrase);

            $key = null;
            try {
                $key = HierarchicalKeyFactory::fromEntropy($seed);
            } catch (\Exception $e) {
                // try again
            }
        } while (!$key);

        return [$mnemonic, $seed, $key];
    }

    /**
     * generate a new mnemonic from some random entropy (512 bit)
     *
     * @param string    $forceEntropy           forced entropy instead of random entropy for testing purposes
     * @return string
     * @throws \Exception
     */
    public function generateNewMnemonic($forceEntropy = null) {
        if ($forceEntropy === null) {
            $random = new Random();
            $entropy = $random->bytes(512 / 8);
        } else {
            $entropy = $forceEntropy;
        }

        return MnemonicFactory::bip39()->entropyToMnemonic($entropy);
    }

    /**
     * get the balance for the wallet
     *
     * @param string    $identifier             the identifier of the wallet
     * @return array
     */
    public function getWalletBalance($identifier) {
        $response = $this->blocktrailClient->get("wallet/{$identifier}/balance", null, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

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
    public function getNewDerivation($identifier, $path) {
        $result = $this->_getNewDerivation($identifier, $path);
        return $result['path'];
    }

    /**
     * get a new derivation number for specified parent path
     *  eg; m/44'/1'/0/0 results in m/44'/1'/0/0/0 and next time in m/44'/1'/0/0/1 and next time in m/44'/1'/0/0/2
     *
     * @param string    $identifier             the identifier of the wallet
     * @param string    $path                   the parent path for which to get a new derivation
     * @return mixed
     */
    public function _getNewDerivation($identifier, $path) {
        $response = $this->blocktrailClient->post("wallet/{$identifier}/path", null, ['path' => $path], RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * get the path (and redeemScript) to specified address
     *
     * @param string $identifier
     * @param string $address
     * @return array
     * @throws \Exception
     */
    public function getPathForAddress($identifier, $address) {
        $response = $this->blocktrailClient->post("wallet/{$identifier}/path_for_address", null, ['address' => $address], RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true)['path'];
    }

    /**
     * send the transaction using the API
     *
     * @param string       $identifier     the identifier of the wallet
     * @param string|array $rawTransaction raw hex of the transaction (should be partially signed)
     * @param array        $paths          list of the paths that were used for the UTXO
     * @param bool         $checkFee       let the server verify the fee after signing
     * @param null         $twoFactorToken
     * @return string                                the complete raw transaction
     * @throws \Exception
     */
    public function sendTransaction($identifier, $rawTransaction, $paths, $checkFee = false, $twoFactorToken = null) {
        $data = [
            'paths' => $paths,
            'two_factor_token' => $twoFactorToken,
        ];

        if (is_array($rawTransaction)) {
            if (array_key_exists('base_transaction', $rawTransaction)
            && array_key_exists('signed_transaction', $rawTransaction)) {
                $data['base_transaction'] = $rawTransaction['base_transaction'];
                $data['signed_transaction'] = $rawTransaction['signed_transaction'];
            } else {
                throw new \InvalidArgumentException("Invalid value for transaction. For segwit transactions, pass ['base_transaction' => '...', 'signed_transaction' => '...']");
            }
        } else {
            $data['raw_transaction'] = $rawTransaction;
        }

        // dynamic TTL for when we're signing really big transactions
        $ttl = max(5.0, count($paths) * 0.25) + 4.0;

        $response = $this->blocktrailClient->post("wallet/{$identifier}/send", ['check_fee' => (int)!!$checkFee], $data, RestClient::AUTH_HTTP_SIG, $ttl);
        $signed = self::jsonDecode($response->body(), true);

        if (!$signed['complete'] || $signed['complete'] == 'false') {
            throw new \Exception("Failed to completely sign transaction");
        }

        // create TX hash from the raw signed hex
        return TransactionFactory::fromHex($signed['hex'])->getTxId()->getHex();
    }

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
     * @throws \Exception
     */
    public function coinSelection($identifier, $outputs, $lockUTXO = false, $allowZeroConf = false, $feeStrategy = Wallet::FEE_STRATEGY_OPTIMAL, $forceFee = null) {
        $args = [
            'lock' => (int)!!$lockUTXO,
            'zeroconf' => (int)!!$allowZeroConf,
            'fee_strategy' => $feeStrategy,
        ];

        if ($forceFee !== null) {
            $args['forcefee'] = (int)$forceFee;
        }

        $response = $this->blocktrailClient->post(
            "wallet/{$identifier}/coin-selection",
            $args,
            $outputs,
            RestClient::AUTH_HTTP_SIG
        );

        \var_export(self::jsonDecode($response->body(), true));

        return self::jsonDecode($response->body(), true);
    }

    /**
     *
     * @param string   $identifier the identifier of the wallet
     * @param bool     $allowZeroConf
     * @param string   $feeStrategy
     * @param null|int $forceFee
     * @param int      $outputCnt
     * @return array
     * @throws \Exception
     */
    public function walletMaxSpendable($identifier, $allowZeroConf = false, $feeStrategy = Wallet::FEE_STRATEGY_OPTIMAL, $forceFee = null, $outputCnt = 1) {
        $args = [
            'zeroconf' => (int)!!$allowZeroConf,
            'fee_strategy' => $feeStrategy,
            'outputs' => $outputCnt,
        ];

        if ($forceFee !== null) {
            $args['forcefee'] = (int)$forceFee;
        }

        $response = $this->blocktrailClient->get(
            "wallet/{$identifier}/max-spendable",
            $args,
            RestClient::AUTH_HTTP_SIG
        );

        return self::jsonDecode($response->body(), true);
    }

    /**
     * @return array        ['optimal_fee' => 10000, 'low_priority_fee' => 5000]
     */
    public function feePerKB() {
        $response = $this->blocktrailClient->get("fee-per-kb");
        return self::jsonDecode($response->body(), true);
    }

    /**
     * get the current price index
     *
     * @return array        eg; ['USD' => 287.30]
     */
    public function price() {
        $response = $this->blocktrailClient->get("price");
        return self::jsonDecode($response->body(), true);
    }

    /**
     * setup webhook for wallet
     *
     * @param string    $identifier         the wallet identifier for which to create the webhook
     * @param string    $webhookIdentifier  the webhook identifier to use
     * @param string    $url                the url to receive the webhook events
     * @return array
     */
    public function setupWalletWebhook($identifier, $webhookIdentifier, $url) {
        $response = $this->blocktrailClient->post("wallet/{$identifier}/webhook", null, ['url' => $url, 'identifier' => $webhookIdentifier], RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * delete webhook for wallet
     *
     * @param string    $identifier         the wallet identifier for which to delete the webhook
     * @param string    $webhookIdentifier  the webhook identifier to delete
     * @return array
     */
    public function deleteWalletWebhook($identifier, $webhookIdentifier) {
        $response = $this->blocktrailClient->delete("wallet/{$identifier}/webhook/{$webhookIdentifier}", null, null, RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * lock a specific unspent output
     *
     * @param     $identifier
     * @param     $txHash
     * @param     $txIdx
     * @param int $ttl
     * @return bool
     */
    public function lockWalletUTXO($identifier, $txHash, $txIdx, $ttl = 3) {
        $response = $this->blocktrailClient->post("wallet/{$identifier}/lock-utxo", null, ['hash' => $txHash, 'idx' => $txIdx, 'ttl' => $ttl], RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true)['locked'];
    }

    /**
     * unlock a specific unspent output
     *
     * @param     $identifier
     * @param     $txHash
     * @param     $txIdx
     * @return bool
     */
    public function unlockWalletUTXO($identifier, $txHash, $txIdx) {
        $response = $this->blocktrailClient->post("wallet/{$identifier}/unlock-utxo", null, ['hash' => $txHash, 'idx' => $txIdx], RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true)['unlocked'];
    }

    /**
     * get all transactions for wallet (paginated)
     *
     * @param  string  $identifier  the wallet identifier for which to get transactions
     * @param  integer $page        pagination: page number
     * @param  integer $limit       pagination: records per page (max 500)
     * @param  string  $sortDir     pagination: sort direction (asc|desc)
     * @return array                associative array containing the response
     */
    public function walletTransactions($identifier, $page = 1, $limit = 20, $sortDir = 'asc') {
        $queryString = [
            'page' => $page,
            'limit' => $limit,
            'sort_dir' => $sortDir
        ];
        $response = $this->blocktrailClient->get("wallet/{$identifier}/transactions", $this->converter->paginationParams($queryString), RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * get all addresses for wallet (paginated)
     *
     * @param  string  $identifier  the wallet identifier for which to get addresses
     * @param  integer $page        pagination: page number
     * @param  integer $limit       pagination: records per page (max 500)
     * @param  string  $sortDir     pagination: sort direction (asc|desc)
     * @return array                associative array containing the response
     */
    public function walletAddresses($identifier, $page = 1, $limit = 20, $sortDir = 'asc') {
        $queryString = [
            'page' => $page,
            'limit' => $limit,
            'sort_dir' => $sortDir
        ];
        $response = $this->blocktrailClient->get("wallet/{$identifier}/addresses", $this->converter->paginationParams($queryString), RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * get all UTXOs for wallet (paginated)
     *
     * @param  string  $identifier  the wallet identifier for which to get addresses
     * @param  integer $page        pagination: page number
     * @param  integer $limit       pagination: records per page (max 500)
     * @param  string  $sortDir     pagination: sort direction (asc|desc)
     * @param  boolean $zeroconf    include zero confirmation transactions
     * @return array                associative array containing the response
     */
    public function walletUTXOs($identifier, $page = 1, $limit = 20, $sortDir = 'asc', $zeroconf = true) {
        $queryString = [
            'page' => $page,
            'limit' => $limit,
            'sort_dir' => $sortDir,
            'zeroconf' => (int)!!$zeroconf,
        ];
        $response = $this->blocktrailClient->get("wallet/{$identifier}/utxos", $this->converter->paginationParams($queryString), RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * get a paginated list of all wallets associated with the api user
     *
     * @param  integer          $page    pagination: page number
     * @param  integer          $limit   pagination: records per page
     * @return array                     associative array containing the response
     */
    public function allWallets($page = 1, $limit = 20) {
        $queryString = [
            'page' => $page,
            'limit' => $limit
        ];
        $response = $this->blocktrailClient->get("wallets", $this->converter->paginationParams($queryString), RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * send raw transaction
     *
     * @param     $txHex
     * @return bool
     */
    public function sendRawTransaction($txHex) {
        $response = $this->blocktrailClient->post("send-raw-tx", null, ['hex' => $txHex], RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * testnet only ;-)
     *
     * @param     $address
     * @param int $amount       defaults to 0.0001 BTC, max 0.001 BTC
     * @return mixed
     * @throws \Exception
     */
    public function faucetWithdrawal($address, $amount = 10000) {
        $response = $this->blocktrailClient->post("faucet/withdrawl", null, [
            'address' => $address,
            'amount' => $amount,
        ], RestClient::AUTH_HTTP_SIG);
        return self::jsonDecode($response->body(), true);
    }

    /**
     * Exists for BC. Remove at major bump.
     *
     * @see faucetWithdrawal
     * @deprecated
     * @param     $address
     * @param int $amount       defaults to 0.0001 BTC, max 0.001 BTC
     * @return mixed
     * @throws \Exception
     */
    public function faucetWithdrawl($address, $amount = 10000) {
        return $this->faucetWithdrawal($address, $amount);
    }

    /**
     * verify a message signed bitcoin-core style
     *
     * @param  string           $message
     * @param  string           $address
     * @param  string           $signature
     * @return boolean
     */
    public function verifyMessage($message, $address, $signature) {
        $adapter = Bitcoin::getEcAdapter();
        $addr = \BitWasp\Bitcoin\Address\AddressFactory::fromString($address);
        if (!$addr instanceof PayToPubKeyHashAddress) {
            throw new \InvalidArgumentException('Can only verify a message with a pay-to-pubkey-hash address');
        }

        /** @var CompactSignatureSerializerInterface $csSerializer */
        $csSerializer = EcSerializer::getSerializer(CompactSignatureSerializerInterface::class, $adapter);
        $signedMessage = new SignedMessage($message, $csSerializer->parse(new Buffer(base64_decode($signature))));

        $signer = new MessageSigner($adapter);
        return $signer->verify($signedMessage, $addr);
    }

    /**
     * Take a base58 or cashaddress, and return only
     * the cash address.
     * This function only works on bitcoin cash.
     * @param string $input
     * @return string
     * @throws BlocktrailSDKException
     */
    public function getLegacyBitcoinCashAddress($input) {
        if ($this->network === "bitcoincash") {
            $address = $this
                ->makeAddressReader([
                    "use_cashaddress" => true
                ])
                ->fromString($input);

            if ($address instanceof CashAddress) {
                $address = $address->getLegacyAddress();
            }

            return $address->getAddress();
        }

        throw new BlocktrailSDKException("Only request a legacy address when using bitcoin cash");
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
    public static function toSatoshiString($btc) {
        return bcmul(sprintf("%.8f", (float)$btc), 100000000, 0);
    }

    /**
     * convert a BTC value to a Satoshi value
     *
     * @param float     $btc
     * @return string
     */
    public static function toSatoshi($btc) {
        return (int)self::toSatoshiString($btc);
    }

    /**
     * json_decode helper that throws exceptions when it fails to decode
     *
     * @param      $json
     * @param bool $assoc
     * @return mixed
     * @throws \Exception
     */
    public static function jsonDecode($json, $assoc = false) {
        if (!$json) {
            throw new \Exception("Can't json_decode empty string [{$json}]");
        }

        $data = json_decode($json, $assoc);

        if ($data === null) {
            throw new \Exception("Failed to json_decode [{$json}]");
        }

        return $data;
    }

    /**
     * sort public keys for multisig script
     *
     * @param PublicKeyInterface[] $pubKeys
     * @return PublicKeyInterface[]
     */
    public static function sortMultisigKeys(array $pubKeys) {
        $result = array_values($pubKeys);
        usort($result, function (PublicKeyInterface $a, PublicKeyInterface $b) {
            $av = $a->getHex();
            $bv = $b->getHex();
            return $av == $bv ? 0 : $av > $bv ? 1 : -1;
        });

        return $result;
    }

    /**
     * read and decode the json payload from a webhook's POST request.
     *
     * @param bool $returnObject    flag to indicate if an object or associative array should be returned
     * @return mixed|null
     * @throws \Exception
     */
    public static function getWebhookPayload($returnObject = false) {
        $data = file_get_contents("php://input");
        if ($data) {
            return self::jsonDecode($data, !$returnObject);
        } else {
            return null;
        }
    }

    public static function normalizeBIP32KeyArray($keys) {
        return Util::arrayMapWithIndex(function ($idx, $key) {
            return [$idx, self::normalizeBIP32Key($key)];
        }, $keys);
    }

    /**
     * @param array|BIP32Key $key
     * @return BIP32Key
     * @throws \Exception
     */
    public static function normalizeBIP32Key($key) {
        if ($key instanceof BIP32Key) {
            return $key;
        }

        if (is_array($key) && count($key) === 2) {
            $path = $key[1];
            $hk = $key[0];

            if (!($hk instanceof HierarchicalKey)) {
                $hk = HierarchicalKeyFactory::fromExtended($hk);
            }

            return BIP32Key::create($hk, $path);
        } else {
            throw new \Exception("Bad Input");
        }
    }

    public function shuffle($arr) {
        \shuffle($arr);
    }
}
