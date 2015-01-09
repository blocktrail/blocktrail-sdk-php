<?php

namespace Blocktrail\SDK;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BitcoinLib;
use BitWasp\BitcoinLib\RawTransaction;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Bitcoin\BIP32Path;

/**
 * Wallet
 *
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
     * @var BlocktrailSDK
     */
    protected $sdk;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * BIP32 master primary private key (m/)
     *
     * @var BIP32Key
     */
    protected $primaryPrivateKey;

    /**
     * BIP32 master backup public key (M/)

     * @var BIP32Key
     */
    protected $backupPublicKey;

    /**
     * map of blocktrail BIP32 public keys
     *  keyed by key index
     *  path should be `M / key_index'`
     *
     * @var BIP32Key[]
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
     * @param BlocktrailSDK                 $sdk                        SDK instance used to do requests
     * @param string                        $identifier                 identifier of the wallet
     * @param array[string, string]         $primaryPrivateKey          should be BIP32 master key m/
     * @param array[string, string]         $backupPublicKey            should be BIP32 master public key M/
     * @param array[array[string, string]]  $blocktrailPublicKeys
     * @param int                           $keyIndex
     * @param bool                          $testnet
     */
    public function __construct(BlocktrailSDK $sdk, $identifier, $primaryPrivateKey, $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $testnet) {
        $this->sdk = $sdk;

        $this->identifier = $identifier;

        $this->primaryPrivateKey = BIP32Key::create($primaryPrivateKey);
        $this->backupPublicKey = BIP32Key::create($backupPublicKey);
        $this->blocktrailPublicKeys = array_map(function($key) {
            return BIP32Key::create($key);
        }, $blocktrailPublicKeys);

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

            list($checkAddress, $checkRedeemScript) = $this->getRedeemScriptByPath($path);

            if ($checkAddress != $address) {
                throw new \Exception("Failed to verify address [{$address}] != [{$checkAddress}]");
            }

            if ($checkRedeemScript != $redeemScript) {
                throw new \Exception("Failed to verify redeemScript [{$redeemScript}] != [{$checkRedeemScript}]");
            }
        } else {
            $path = $this->sdk->getNewDerivation($this->identifier, (string)$path);
        }

        return (string)$path;
    }

    /**
     * @param string|BIP32Path  $path
     * @return BIP32Key|false
     * @throws \Exception
     *
     * @TODO: hmm?
     */
    protected function getParentPublicKey($path) {
        $path = BIP32Path::path($path)->parent()->publicPath();

        if ($path->count() <= 2) {
            return false;
        }

        if ($path->isHardened()) {
            return false;
        }

        if (!isset($this->pubKeys[(string)$path])) {
            $this->pubKeys[(string)$path] = $this->primaryPrivateKey->buildKey($path);
        }

        return $this->pubKeys[(string)$path];
    }

    /**
     * get address for the specified path
     *
     * @param string|BIP32Path  $path
     * @return string
     */
    public function getAddressByPath($path) {
        $path = (string)BIP32Path::path($path)->privatePath();
        if (!isset($this->derivations[$path])) {
            list($address, $redeemScript) = $this->getRedeemScriptByPath($path);

            $this->derivations[$path] = $address;
            $this->derivationsByAddress[$address] = $path;
        }

        return $this->derivations[$path];
    }

    /**
     * get address and redeemScript for specified path
     *
     * @param string    $path
     * @return array[string, string]     [address, redeemScript]
     */
    public function getRedeemScriptByPath($path) {
        $path = BIP32Path::path($path);

        // optimization to avoid doing BitcoinLib::private_key_to_public_key too much
        if ($pubKey = $this->getParentPublicKey($path)) {
            $key = $pubKey->buildKey($path->publicPath());
        } else {
            $key = $this->primaryPrivateKey->buildKey($path);
        }

        return $this->getRedeemScriptFromKey($key, $path);
    }

    /**
     * @param BIP32Key          $key
     * @param string|BIP32Path  $path
     * @return string
     */
    protected function getAddressFromKey(BIP32Key $key, $path) {
        return $this->getRedeemScriptFromKey($key, $path)[0];
    }

    /**
     * @param BIP32Key          $key
     * @param string|BIP32Path  $path
     * @return string[]                 [address, redeemScript]
     * @throws \Exception
     */
    protected function getRedeemScriptFromKey(BIP32Key $key, $path) {
        $path = BIP32Path::path($path)->publicPath();

        $blocktrailPublicKey = $this->getBlocktrailPublicKey($path);

        $multiSig = RawTransaction::create_multisig(
            2,
            RawTransaction::sort_multisig_keys([
                $key->buildKey($path)->publicKey(),
                $this->backupPublicKey->buildKey($path->unhardenedPath())->publicKey(),
                $blocktrailPublicKey->buildKey($path)->publicKey()
            ])
        );

        return [$multiSig['address'], $multiSig['redeemScript']];
    }

    /**
     * @param string|BIP32Path  $path
     * @return BIP32Key
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
     * @return int[]            [confirmed, unconfirmed]
     */
    public function doDiscovery() {
        return $this->sdk->doWalletDiscovery($this->identifier);
    }

    /**
     * create, sign and send a transaction
     *
     * @param array     $pay            array['address' => int] coins to send
     * @param string    $changeAddress  (optional) change address to use (autogenerated if NULL)
     * @return                          string the txid / transaction hash
     * @throws \Exception
     */
    public function pay(array $pay, $changeAddress = null) {
        foreach ($pay as $address => $value) {
            if (!BitcoinLib::validate_address($address)) {
                throw new \Exception("Invalid address [{$address}]");
            }

            if (strpos($value, ".") !== false || strpos($value, "," !== false)) {
                throw new \Exception("Values should be in Satoshis");
            }

            if ($value <= Blocktrail::DUST) {
                throw new \Exception("Values should be more than dust (" . Blocktrail::DUST . ")");
            }
        }

        // the output structure equals $pay right now ...
        $send = $pay;

        // get the data we should use for this transaction
        $coinSelection = $this->coinSelection($pay);
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

        // create raw transaction
        $inputs = array_map(function ($utxo) {
            return [
                'txid' => $utxo['hash'],
                'vout' => $utxo['idx'],
                'scriptPubKey' => $utxo['scriptpubkey_hex'],
                'value' => BlocktrailSDK::toBTC($utxo['value']),
                'address' => $utxo['address'],
                'path' => $utxo['path'],
                'redeemScript' => $utxo['redeem_script']
            ];
        }, $utxos);
        $outputs = [];
        foreach ($send as $address => $value) {
            $outputs[$address] = BlocktrailSDK::toBTC($value);
        }
        $raw_transaction = RawTransaction::create($inputs, $outputs);

        if (!$raw_transaction) {
            throw new \Exception("Failed to create raw transaction");
        }

        // sign the transaction with our keys
        $signed = $this->signTransaction($raw_transaction, $inputs);

        if (!$signed['sign_count']) {
            throw new \Exception("Failed to partially sign transaction");
        }

        // send the transaction
        $finished = $this->sendTransaction($signed['hex'], array_column($utxos, 'path'));

        return $finished;
    }

    /**
     * determine how much fee is required based on the inputs and outputs
     *  this is an estimation, not a proper 100% correct calculation
     *
     * @param array[]   $utxos
     * @param array[]   $outputs
     * @return int
     */
    public function determineFee($utxos, $outputs) {
        $txoutSize = (count($outputs) * 34);

        $txinSize = 0;

        foreach ($utxos as $utxo) {
            $multisig = "2of3"; // we only do multisig right now ...

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

        $sizeKB = ceil($size / 1000);

        return $sizeKB * self::BASE_FEE;
    }

    /**
     * determine how much change is left over based on the inputs and outputs and the fee
     *
     * @param array[]   $utxos
     * @param array[]   $outputs
     * @param int       $fee
     * @return int
     */
    public function determineChange($utxos, $outputs, $fee) {
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
    public function signTransaction($raw_transaction, array $inputs) {
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

        BIP32::bip32_keys_to_wallet($wallet, array_map(function(BIP32Key $key) { return $key->tuple(); }, $keys));
        RawTransaction::redeem_scripts_to_wallet($wallet, $redeemScripts);

        return RawTransaction::sign($wallet, $raw_transaction, json_encode($inputs));
    }

    /**
     * send the transaction using the API
     *
     * @param string    $signed
     * @param string[]  $paths
     * @return string           the complete raw transaction
     * @throws \Exception
     */
    public function sendTransaction($signed, $paths) {
        return $this->sdk->sendTransaction($this->identifier, $signed, $paths);
    }

    /**
     * use the API to get the best inputs to use based on the outputs
     *
     * @param array[]   $outputs
     * @param bool      $lockUTXO
     * @return array
     */
    public function coinSelection($outputs, $lockUTXO = true) {
        return $this->sdk->coinSelection($this->identifier, $outputs, $lockUTXO);
    }

    /**
     * delete the wallet
     *
     * @return mixed
     */
    public function deleteWallet() {
        list($checksumAddress, $signature) = $this->createChecksumVerificationSignature();
        return $this->sdk->deleteWallet($this->identifier, $checksumAddress, $signature)['deleted'];
    }

    /**
     * create checksum to verify ownership of the master primary key
     *
     * @return string[]     [address, signature]
     */
    public function createChecksumVerificationSignature() {
        $import = BIP32::import($this->primaryPrivateKey->key());

        $public = $this->primaryPrivateKey->publicKey();
        $address = BitcoinLib::public_key_to_address($public, $import['version']);

        return [$address, BitcoinLib::signMessage($address, $import)];
    }
}
