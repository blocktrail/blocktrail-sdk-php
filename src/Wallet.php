<?php

namespace Blocktrail\SDK;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BitcoinLib;
use BitWasp\BitcoinLib\RawTransaction;
use Blocktrail\SDK\Bitcoin\BIP32Path;

/**
 * Wallet
 *
 */
class Wallet {

    const BASE_FEE = 10000;

    /**
     * @var BlocktrailSDK
     */
    protected $sdk;

    protected $identifier;

    protected $primaryPrivateKey;
    protected $backupPublicKey;
    protected $blocktrailPublicKeys;

    /**
     * the 'Blocktrail Key Index'
     *
     * @var int
     */
    protected $keyIndex;

    protected $testnet;

    protected $pubKeys = [];

    protected $derivations = [];
    protected $derivationsByAddress = [];

    /**
     * @var WalletPath
     */
    protected $walletPath;

    public function __construct(BlocktrailSDK $sdk, $identifier, $primaryPrivateKey, $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $testnet) {
        $this->sdk = $sdk;

        $this->identifier = $identifier;

        $this->primaryPrivateKey = $primaryPrivateKey;
        $this->backupPublicKey = $backupPublicKey;
        $this->blocktrailPublicKeys = $blocktrailPublicKeys;

        $this->testnet = $testnet;
        $this->keyIndex = $keyIndex;

        $this->walletPath = WalletPath::create($this->keyIndex);
    }

    public function getIdentifier() {
        return $this->identifier;
    }

    protected function getNewDerivation() {
        $path = $this->walletPath->path()->last("*");
        $path = $this->sdk->getNewDerivation($this->identifier, (string)$path);

        return $path;
    }

    protected function getParentPublicKey($path) {
        $path = BIP32Path::path($path)->parent();

        if ($path->count() <= 2) {
            return false;
        }

        if ($path->isHardened()) {
            return false;
        }

        $path = $path->parent();

        if (!isset($this->pubKeys[strtoupper($path)])) {
            $privKey = BIP32::build_key($this->primaryPrivateKey, $path);
            $pubKey = BIP32::extended_private_to_public($privKey[0]);

            $this->pubKeys[strtoupper($path)] = $pubKey;
        }

        return [$this->pubKeys[strtoupper($path)], strtoupper($path)];
    }

    /**
     * get the 2of3 multisig address for:
     *  - the last derived private key
     *  - the backup public key
     *  - the blocktrail public key
     *
     * will create a new derived private key if there are no keys yet
     *
     * @param $path
     * @return string
     */
    public function getAddress($path) {
        $path = (string)BIP32Path::path($path)->privatePath();

        if (!isset($this->derivations[$path])) {
            // optimization to avoid doing BitcoinLib::private_key_to_public_key too much
            if ($pubKey = $this->getParentPublicKey($path)) {
                $key = BIP32::build_key($pubKey, strtoupper($path));
            } else {
                $key = BIP32::build_key($this->primaryPrivateKey, $path);
            }

            $address = $this->getAddressFromKey($key, $path);

            $this->derivations[$path] = $address;
            $this->derivationsByAddress[$address] = $path;
        }

        return $this->derivations[$path];
    }

    protected function getAddressFromKey($key, $path) {
        $path = BIP32Path::path($path)->publicPath();

        $blocktrailPublicKey = $this->getBlocktrailPublicKey($path);

        $multiSig = RawTransaction::create_multisig(
            2,
            RawTransaction::sort_multisig_keys([
                BIP32::extract_public_key(BIP32::build_key($key, (string)$path)),
                BIP32::extract_public_key(BIP32::build_key($this->backupPublicKey, (string)$path->unhardenedPath())),
                BIP32::extract_public_key(BIP32::build_key($blocktrailPublicKey, (string)$path))
            ])
        );

        $address = $multiSig['address'];

        return $address;
    }

    public function getBlocktrailPublicKey($path) {
        $path = BIP32Path::path($path);

        $keyIndex = str_replace("'", "", $path[1]);

        if (!isset($this->blocktrailPublicKeys[$keyIndex])) {
            throw new \Exception("No blocktrail publickey for key index [{$keyIndex}]");
        }

        return $this->blocktrailPublicKeys[$keyIndex];
    }

    /**
     * generate a new derived private key and return the new path and address for it
     *
     * @return string
     */
    public function getNewAddressPair() {
        $path = $this->getNewDerivation();

        $address = $this->getAddress($path);

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

    public function getBalance() {
        $balanceInfo = $this->sdk->getWalletBalance($this->identifier);

        return [$balanceInfo['confirmed'], $balanceInfo['unconfirmed']];
    }

    public function doDiscovery() {
        return $this->sdk->doWalletDiscovery($this->identifier);
    }

    /**
     * create, sign and send a transaction
     *
     * @param array     $pay array['address' => int] coins to send
     * @param string    $changeAddress
     * @return string the txid / transaction hash
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
     * @param $utxos
     * @param $outputs
     * @return integer
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
     * @param $utxos
     * @param $outputs
     * @param $fee
     * @return integer
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
     * @param       $raw_transaction
     * @param array $inputs
     * @return array
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
                $key = BIP32::build_key($this->primaryPrivateKey, (string)$path);
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

        BIP32::bip32_keys_to_wallet($wallet, $keys);
        RawTransaction::redeem_scripts_to_wallet($wallet, $redeemScripts);

        return RawTransaction::sign($wallet, $raw_transaction, json_encode($inputs));
    }

    /**
     * send the transaction using the API
     *
     * @param string $signed
     * @param        $paths
     * @return string           the complete raw transaction
     * @throws \Exception
     */
    public function sendTransaction($signed, $paths) {
        return $this->sdk->sendTransaction($this->identifier, $signed, $paths);
    }

    /**
     * use the API to get the best inputs to use based on the outputs
     *
     * @param      $outputs
     * @param bool $lockUTXO
     * @return array
     */
    public function coinSelection($outputs, $lockUTXO = true) {
        return $this->sdk->coinSelection($this->identifier, $outputs, $lockUTXO);
    }

    public function deleteWallet() {
        list($checksumAddress, $signature) = $this->createChecksumVerificationSignature();
        return $this->sdk->deleteWallet($this->identifier, $checksumAddress, $signature)['deleted'];
    }

    public function createChecksumVerificationSignature() {
        $import = BIP32::import($this->primaryPrivateKey[0]);

        $public = BitcoinLib::private_key_to_public_key($import['key'], true);
        $address = BitcoinLib::public_key_to_address($public, $import['version']);

        return [$address, BitcoinLib::signMessage($address, $import)];
    }
}
