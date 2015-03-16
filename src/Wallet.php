<?php

namespace Blocktrail\SDK;

use Afk11\Bitcoin\Address\AddressFactory;
use Afk11\Bitcoin\Key\HierarchicalKey;
use Afk11\Bitcoin\Script\RedeemScript;
use Afk11\Bitcoin\Script\ScriptFactory;
use Afk11\Bitcoin\Transaction\TransactionFactory;
use Afk11\Bitcoin\Transaction\TransactionInput;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Bitcoin\BIP32Path;

/**
 * Class Wallet
 */
class Wallet {

    const BASE_FEE = 10000;

    /**
     * development / debug setting
     *  when getting a new derivation from the API,
     *  will verify address / redeeemScript with the values the API provides
     */
    const VERIFY_NEW_DERIVATION = true;

    /**
     * @var BlocktrailSDKInterface
     */
    protected $sdk;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * BIP39 Mnemonic for the master primary private key
     *
     * @var string
     */
    protected $primaryMnemonic;

    /**
     * BIP32 master primary private key (m/)
     *
     * @var HierarchicalKey
     */
    protected $primaryPrivateKey;

    /**
     * BIP32 master backup public key (M/)

     * @var HierarchicalKey
     */
    protected $backupPublicKey;

    /**
     * map of blocktrail BIP32 public keys
     *  keyed by key index
     *  path should be `M / key_index'`
     *
     * @var HierarchicalKey[]
     */
    protected $blocktrailPublicKeys;

    /**
     * the 'Blocktrail Key Index' that is used for new addresses
     *
     * @var int
     */
    protected $keyIndex;

    /**
     * testnet yes / no
     *
     * @var bool
     */
    protected $testnet;

    /**
     * cache of public keys, by path
     *
     * @var BIP32Key[]
     */
    protected $pubKeys = [];

    /**
     * cache of address / redeemScript, by path
     *
     * @var string[][]      [[address, redeemScript)], ]
     */
    protected $derivations = [];

    /**
     * reverse cache of paths by address
     *
     * @var string[]
     */
    protected $derivationsByAddress = [];

    /**
     * @var WalletPath
     */
    protected $walletPath;

    /**
     * @param BlocktrailSDKInterface        $sdk                        SDK instance used to do requests
     * @param string                        $identifier                 identifier of the wallet
     * @param string                        $primaryMnemonic
     * @param HierarchicalKey               $primaryPrivateKey          should be BIP32 master key m/
     * @param HierarchicalKey               $backupPublicKey            should be BIP32 master public key M/
     * @param HierarchicalKey[]             $blocktrailPublicKeys
     * @param int                           $keyIndex
     * @param bool                          $testnet
     */
    public function __construct(BlocktrailSDKInterface $sdk, $identifier, $primaryMnemonic, HierarchicalKey $primaryPrivateKey, HierarchicalKey $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $testnet) {
        $this->sdk = $sdk;

        $this->identifier = $identifier;

        $this->primaryMnemonic = $primaryMnemonic;
        $this->primaryPrivateKey = $primaryPrivateKey;
        $this->backupPublicKey = $backupPublicKey;
        $this->blocktrailPublicKeys = $blocktrailPublicKeys;

        $this->testnet = $testnet;
        $this->keyIndex = $keyIndex;

        $this->walletPath = WalletPath::create($this->keyIndex);
    }

    /**
     * return the wallet identifier
     *
     * @return string
     */
    public function getIdentifier() {
        return $this->identifier;
    }

    /**
     * return the wallet primary mnemonic (for backup purposes)
     *
     * @return string
     */
    public function getPrimaryMnemonic() {
        return $this->primaryMnemonic;
    }

    /**
     * return list of Blocktrail co-sign extended public keys
     *
     * @return array[]      [ [xpub, path] ]
     */
    public function getBlocktrailPublicKeys() {
        return array_map(function (BIP32Key $key) {
            return $key->tuple();
        }, $this->blocktrailPublicKeys);
    }

    /**
     * upgrade wallet to different blocktrail cosign key
     *
     * @param $keyIndex
     * @throws \Exception
     */
    public function upgradeKeyIndex($keyIndex) {
        $walletPath = WalletPath::create($keyIndex);

        // do the upgrade to the new 'key_index'
        $primaryPublicKey = BIP32::extended_private_to_public(BIP32::build_key($this->primaryPrivateKey->tuple(), (string)$walletPath->keyIndexPath()));
        $result = $this->sdk->upgradeKeyIndex($this->identifier, $keyIndex, $primaryPublicKey);

        $this->keyIndex = $keyIndex;
        $this->walletPath = $walletPath;

        // update the blocktrail public keys
        foreach ($result['blocktrail_public_keys'] as $keyIndex => $pubKey) {
            if (!isset($this->blocktrailPublicKeys[$keyIndex])) {
                $this->blocktrailPublicKeys[$keyIndex] = BIP32Key::create($pubKey);
            }
        }
    }

    /**
     * get a new BIP32 derivation for the next (unused) address
     *  by requesting it from the API
     *
     * @return string
     * @throws \Exception
     */
    protected function getNewDerivation() {
        $path = $this->walletPath->path()->last("*");

        if (self::VERIFY_NEW_DERIVATION) {
            $new = $this->sdk->_getNewDerivation($this->identifier, (string)$path);

            $path = $new['path'];
            $address = $new['address'];
            $redeemScript = $new['redeem_script'];

            $redeemScript = $this->getRedeemScriptByPath($path);
            $checkRedeemScript = (string)$redeemScript;
            $checkAddress = (string)$redeemScript->getAddress();

            if ($checkAddress != $address) {
                throw new \Exception("Failed to verify that address from API [{$address}] matches address locally [{$checkAddress}]");
            }

            if ($checkRedeemScript != $redeemScript) {
                throw new \Exception("Failed to verify that redeemScript from API [{$redeemScript}] matches address locally [{$checkRedeemScript}]");
            }
        } else {
            $path = $this->sdk->getNewDerivation($this->identifier, (string)$path);
        }

        return (string)$path;
    }

    /**
     * get address for the specified path
     *
     * @param string|BIP32Path  $path
     * @return string
     */
    public function getAddressByPath($path) {
        return $this->getRedeemScriptByPath($path)->getAddress()->getAddress();
    }

    /**
     * get address and redeemScript for specified path
     *
     * @param string    $path
     * @return RedeemScript
     */
    public function getRedeemScriptByPath($path) {
        $path = BIP32Path::path($path)->publicPath();

        $blocktrailPublicKey = $this->getBlocktrailPublicKey($path);

        $redeemScript = ScriptFactory::multisig(2, [
            $this->primaryPrivateKey->derivePath((string)$path)->getPublicKey(),
            $this->backupPublicKey->derivePath((string)$path->unhardenedPath())->getPublicKey(),
            $blocktrailPublicKey->derivePath((string)$path->remove(0))->getPublicKey(),
        ], true);

        return $redeemScript;
    }

    /**
     * @param string|BIP32Path  $path
     * @return HierarchicalKey
     * @throws \Exception
     */
    public function getBlocktrailPublicKey($path) {
        $path = BIP32Path::path($path);

        $keyIndex = str_replace("'", "", $path[1]);

        if (!isset($this->blocktrailPublicKeys[$keyIndex])) {
            throw new \Exception("No blocktrail publickey for key index [{$keyIndex}]");
        }

        return $this->blocktrailPublicKeys[$keyIndex];
    }

    /**
     * generate a new derived key and return the new path and address for it
     *
     * @return string[]     [path, address]
     */
    public function getNewAddressPair() {
        $path = $this->getNewDerivation();
        $address = $this->getAddressByPath($path);

        return [$path, $address];
    }

    /**
     * generate a new derived private key and return the new address for it
     *
     * @return string
     */
    public function getNewAddress() {
        return $this->getNewAddressPair()[1];
    }

    /**
     * get the balance for the wallet
     *
     * @return int[]            [confirmed, unconfirmed]
     */
    public function getBalance() {
        $balanceInfo = $this->sdk->getWalletBalance($this->identifier);

        return [$balanceInfo['confirmed'], $balanceInfo['unconfirmed']];
    }

    /**
     * do wallet discovery (slow)
     *
     * @param int   $gap        the gap setting to use for discovery
     * @return int[]            [confirmed, unconfirmed]
     */
    public function doDiscovery($gap = 200) {
        $balanceInfo = $this->sdk->doWalletDiscovery($this->identifier, $gap);

        return [$balanceInfo['confirmed'], $balanceInfo['unconfirmed']];
    }

    /**
     * create, sign and send a transaction
     *
     * @param array  $pay                   array['address' => int] coins to send
     * @param string $changeAddress         (optional) change address to use (autogenerated if NULL)
     * @param bool   $allowZeroConf
     * @param bool   $randomizeChangeIdx    randomize the location of the change (for increased privacy / anonimity)
     * @return string                       the txid / transaction hash
     * @throws \Exception
     */
    public function pay(array $pay, $changeAddress = null, $allowZeroConf = false, $randomizeChangeIdx = true) {
        foreach ($pay as $address => $value) {
            // @TODO
            if (!AddressFactory::fromString($address)) {
                throw new \Exception("Invalid address [{$address}]");
            }

            // using this 'dirty' way of checking for a float since there's no other reliable way in PHP
            if (!is_int($value)) {
                throw new \Exception("Values should be in Satoshis (int)");
            }

            if ($value <= Blocktrail::DUST) {
                throw new \Exception("Values should be more than dust (" . Blocktrail::DUST . ")");
            }
        }

        // the output structure equals $pay right now ...
        $send = $pay;

        // get the data we should use for this transaction
        $coinSelection = $this->coinSelection($pay, true, $allowZeroConf);
        $utxos = $coinSelection['utxos'];
        $fee = $coinSelection['fee'];
        $change = $coinSelection['change'];

        // validate the change to make sure the API is correct
        if ($this->determineChange($utxos, $send, $fee) != $change) {
            throw new \Exception("the amount of change suggested by the coin selection seems incorrect");
        }

        // only add a change output if there's change
        if ($change > 0) {
            if (!$changeAddress) {
                $changeAddress = $this->getNewAddress();
            }

            // this should be impossible, but checking for it anyway
            if (isset($send[$changeAddress])) {
                throw new \Exception("Change address is already part of the outputs");
            }

            // add the change output
            $send[$changeAddress] = $change;
        }

        // validate the fee to make sure the API is correct
        if (($determinedFee = $this->determineFee($utxos, $send)) < $fee) {
            throw new \Exception("the fee suggested by the coin selection ({$fee}) seems incorrect ({$determinedFee})");
        }

        $txb = TransactionFactory::builder();

        foreach($utxos as $utxo) {
            var_dump($utxo);
            $input = new TransactionInput($utxo['hash'], $utxo['idx'], ScriptFactory::fromHex($utxo['scriptpubkey_hex']));
            $txb->addInput($input);
        }

        $addresses = array_keys($send);
        // outputs should be randomized to make the change harder to detect
        if ($randomizeChangeIdx) {
            shuffle($addresses);
        }
        foreach ($addresses as $address) {
            $value = $send[$address];
            $txb->payToAddress(AddressFactory::fromString($address), $value);
        }

        foreach ($utxos as $i => $utxo) {
            $redeemScript = $this->getRedeemScriptByPath($utxo['path']);
            $key = $this->primaryPrivateKey->derivePath($utxo['path']);
            $txb->signInputWithKey($key, $i, $redeemScript);
        }

        $tx = $txb->getTransaction();

        // send the transaction
        $finished = $this->sendTransaction((string)$tx, array_column($utxos, 'path'), true);

        return $finished;
    }

    /**
     * only supports estimating fee for 2of3 multsig UTXOs
     *
     * @param int $utxoCnt      number of unspent inputs in transaction
     * @param int $outputCnt    number of outputs in transaction
     * @return float
     */
    public static function estimateFee($utxoCnt, $outputCnt) {
        $txoutSize = ($outputCnt * 34);

        $txinSize = 0;

        for ($i=0; $i<$utxoCnt; $i++) {
            // @TODO: proper size calculation, we only do multisig right now so it's hardcoded and then we guess the size ...
            $multisig = "2of3";

            if ($multisig) {
                $sigCnt = 2;
                $msig = explode("of", $multisig);
                if (count($msig) == 2 && is_numeric($msig[0])) {
                    $sigCnt = $msig[0];
                }

                $txinSize += array_sum([
                    32, // txhash
                    4, // idx
                    72 * $sigCnt, // sig
                    106, // script
                    4, // pad
                    4, // sequence
                ]);
            } else {
                $txinSize += array_sum([
                    32, // txhash
                    4, // idx
                    72, // sig
                    32, // script
                    4, // ?
                    4, // sequence
                ]);
            }
        }

        $size = 4 + $txoutSize + $txinSize + 4;

        $sizeKB = (int)ceil($size / 1000);

        return $sizeKB * self::BASE_FEE;
    }

    /**
     * determine how much fee is required based on the inputs and outputs
     *  this is an estimation, not a proper 100% correct calculation
     *
     * @param array[]   $utxos
     * @param array[]   $outputs
     * @return int
     */
    protected function determineFee($utxos, $outputs) {
        $utxoCnt = count($utxos);
        $outputCnt = count($outputs);

        return self::estimateFee($utxoCnt, $outputCnt);
    }

    /**
     * determine how much change is left over based on the inputs and outputs and the fee
     *
     * @param array[]   $utxos
     * @param array[]   $outputs
     * @param int       $fee
     * @return int
     */
    protected function determineChange($utxos, $outputs, $fee) {
        $inputsTotal = array_sum(array_map(function ($utxo) {
            return $utxo['value'];
        }, $utxos));
        $outputsTotal = array_sum($outputs);

        return $inputsTotal - $outputsTotal - $fee;
    }

    /**
     * sign a raw transaction with the private keys that we have
     *
     * @param string    $raw_transaction
     * @param array[]   $inputs
     * @return array                        response from RawTransaction::sign
     * @throws \Exception
     */
    protected function signTransaction($raw_transaction, array $inputs) {
        $wallet = [];
        $keys = [];
        $redeemScripts = [];

        foreach ($inputs as $input) {
            $redeemScript = null;
            $key = null;

            if (isset($input['redeemScript'], $input['path'])) {
                $redeemScript = $input['redeemScript'];
                $path = BIP32Path::path($input['path'])->privatePath();
                $key = $this->primaryPrivateKey->buildKey($path);
                $address = $this->getAddressFromKey($key, $path);

                if ($address != $input['address']) {
                    throw new \Exception("Generated address does not match expected address!");
                }
            } else {
                throw new \Exception("No redeemScript/path for input");
            }

            if ($redeemScript && $key) {
                $keys[] = $key;
                $redeemScripts[] = $redeemScript;
            }
        }

        BIP32::bip32_keys_to_wallet($wallet, array_map(function (BIP32Key $key) {
            return $key->tuple();
        }, $keys));
        RawTransaction::redeem_scripts_to_wallet($wallet, $redeemScripts);

        return RawTransaction::sign($wallet, $raw_transaction, json_encode($inputs));
    }

    /**
     * send the transaction using the API
     *
     * @param string    $signed
     * @param string[]  $paths
     * @param bool      $checkFee
     * @return string           the complete raw transaction
     * @throws \Exception
     */
    protected function sendTransaction($signed, $paths, $checkFee = false) {
        return $this->sdk->sendTransaction($this->identifier, $signed, $paths, $checkFee);
    }

    /**
     * use the API to get the best inputs to use based on the outputs
     *
     * @param array[] $outputs
     * @param bool    $lockUTXO
     * @param bool    $allowZeroConf
     * @return array
     */
    protected function coinSelection($outputs, $lockUTXO = true, $allowZeroConf = false) {
        return $this->sdk->coinSelection($this->identifier, $outputs, $lockUTXO, $allowZeroConf);
    }

    /**
     * delete the wallet
     *
     * @param bool $force       ignore warnings (such as non-zero balance)
     * @return mixed
     */
    public function deleteWallet($force = false) {
        list($checksumAddress, $signature) = $this->createChecksumVerificationSignature();
        return $this->sdk->deleteWallet($this->identifier, $checksumAddress, $signature, $force)['deleted'];
    }

    /**
     * create checksum to verify ownership of the master primary key
     *
     * @return string[]     [address, signature]
     */
    protected function createChecksumVerificationSignature() {
        $import = BIP32::import($this->primaryPrivateKey->key());

        $public = $this->primaryPrivateKey->publicKey();
        $address = BitcoinLib::public_key_to_address($public, $import['version']);

        return [$address, BitcoinLib::signMessage($address, $import)];
    }

    /**
     * setup a webhook for our wallet
     *
     * @param string    $url            URL to receive webhook events
     * @param string    $identifier     identifier for the webhook, defaults to WALLET-{$this->identifier}
     * @return array
     */
    public function setupWebhook($url, $identifier = null) {
        $identifier = $identifier ?: "WALLET-{$this->identifier}";
        return $this->sdk->setupWalletWebhook($this->identifier, $identifier, $url);
    }

    /**
     * @param string    $identifier     identifier for the webhook, defaults to WALLET-{$this->identifier}
     * @return mixed
     */
    public function deleteWebhook($identifier = null) {
        $identifier = $identifier ?: "WALLET-{$this->identifier}";
        return $this->sdk->deleteWalletWebhook($this->identifier, $identifier);
    }

    /**
     * get all transactions for the wallet (paginated)
     *
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page (max 500)
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function transactions($page = 1, $limit = 20, $sortDir = 'asc') {
        return $this->sdk->walletTransactions($this->identifier, $page, $limit, $sortDir);
    }

    /**
     * get all addresses for the wallet (paginated)
     *
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page (max 500)
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function addresses($page = 1, $limit = 20, $sortDir = 'asc') {
        return $this->sdk->walletAddresses($this->identifier, $page, $limit, $sortDir);
    }

    /**
     * get all UTXOs for the wallet (paginated)
     *
     * @param  integer $page    pagination: page number
     * @param  integer $limit   pagination: records per page (max 500)
     * @param  string  $sortDir pagination: sort direction (asc|desc)
     * @return array            associative array containing the response
     */
    public function utxos($page = 1, $limit = 20, $sortDir = 'asc') {
        return $this->sdk->walletUTXOs($this->identifier, $page, $limit, $sortDir);
    }
}
