<?php

namespace Blocktrail\SDK;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BIP39\BIP39;
use BitWasp\BitcoinLib\BitcoinLib;
use BitWasp\BitcoinLib\RawTransaction;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Bitcoin\BIP32Path;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

/**
 * Class Wallet
 */
class Wallet implements WalletInterface {

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
     * @var BIP32Key
     */
    protected $primaryPrivateKey;

    /**
     * @var BIP32Key[]
     */
    protected $primaryPublicKeys;

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
     * 'bitcoin'
     *
     * @var string
     */
    protected $network;

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

    private $checksum;

    private $locked = true;

    protected $optimalFeePerKB;
    protected $lowPriorityFeePerKB;
    protected $feePerKBAge;

    /**
     * @param BlocktrailSDKInterface        $sdk                        SDK instance used to do requests
     * @param string                        $identifier                 identifier of the wallet
     * @param string                        $primaryMnemonic
     * @param array[string, string]         $primaryPublicKeys
     * @param array[string, string]         $backupPublicKey            should be BIP32 master public key M/
     * @param array[array[string, string]]  $blocktrailPublicKeys
     * @param int                           $keyIndex
     * @param string                        $network
     * @param bool                          $testnet
     * @param string                        $checksum
     */
    public function __construct(BlocktrailSDKInterface $sdk, $identifier, $primaryMnemonic, $primaryPublicKeys, $backupPublicKey, $blocktrailPublicKeys, $keyIndex, $network, $testnet, $checksum) {
        $this->sdk = $sdk;

        $this->identifier = $identifier;

        $this->primaryMnemonic = $primaryMnemonic;
        $this->backupPublicKey = BIP32Key::create($backupPublicKey);
        $this->primaryPublicKeys = array_map(function ($key) {
            return BIP32Key::create($key);
        }, $primaryPublicKeys);
        $this->blocktrailPublicKeys = array_map(function ($key) {
            return BIP32Key::create($key);
        }, $blocktrailPublicKeys);

        $this->network = $network;
        $this->testnet = $testnet;
        $this->keyIndex = $keyIndex;
        $this->checksum = $checksum;

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
     * unlock wallet so it can be used for payments
     *
     * @param          $options ['primary_private_key' => key] OR ['passphrase' => pass]
     * @param callable $fn
     * @return bool
     * @throws \Exception
     */
    public function unlock($options, callable $fn = null) {
        // explode the wallet data
        $password = isset($options['passphrase']) ? $options['passphrase'] : (isset($options['password']) ? $options['password'] : null);
        $primaryMnemonic = $this->primaryMnemonic;
        $primaryPrivateKey = isset($options['primary_private_key']) ? $options['primary_private_key'] : null;

        if ($primaryMnemonic && $primaryPrivateKey) {
            throw new \InvalidArgumentException("Can't specify Primary Mnemonic and Primary PrivateKey");
        }

        if (!$primaryMnemonic && !$primaryPrivateKey) {
            throw new \InvalidArgumentException("Can't init wallet with Primary Mnemonic or Primary PrivateKey");
        }

        if ($primaryMnemonic && !$password) {
            throw new \InvalidArgumentException("Can't init wallet with Primary Mnemonic without a passphrase");
        }

        if ($primaryPrivateKey) {
            if (is_string($primaryPrivateKey)) {
                $primaryPrivateKey = [$primaryPrivateKey, "m"];
            }
        } else {
            // convert the mnemonic to a seed using BIP39 standard
            $primarySeed = BIP39::mnemonicToSeedHex($primaryMnemonic, $password);
            // create BIP32 private key from the seed
            $primaryPrivateKey = BIP32::master_key($primarySeed, $this->network, $this->testnet);
        }

        $this->primaryPrivateKey = BIP32Key::create($primaryPrivateKey);

        // create checksum (address) of the primary privatekey to compare to the stored checksum
        $checksum = BIP32::key_to_address($primaryPrivateKey[0]);
        if ($checksum != $this->checksum) {
            throw new \Exception("Checksum [{$checksum}] does not match [{$this->checksum}], most likely due to incorrect password");
        }

        $this->locked = false;

        // if the response suggests we should upgrade to a different blocktrail cosigning key then we should
        if (isset($data['upgrade_key_index'])) {
            $this->upgradeKeyIndex($data['upgrade_key_index']);
        }

        if ($fn) {
            $fn($this);
            $this->lock();
        }
    }

    /**
     * lock the wallet (unsets primary private key)
     *
     * @return void
     */
    public function lock() {
        $this->primaryPrivateKey = null;
        $this->locked = true;
    }

    /**
     * check if wallet is locked
     *
     * @return bool
     */
    public function isLocked() {
        return $this->locked;
    }

    /**
     * upgrade wallet to different blocktrail cosign key
     *
     * @param $keyIndex
     * @return bool
     * @throws \Exception
     */
    public function upgradeKeyIndex($keyIndex) {
        if ($this->locked) {
            throw new \Exception("Wallet needs to be unlocked to upgrade key index");
        }

        $walletPath = WalletPath::create($keyIndex);

        // do the upgrade to the new 'key_index'
        $primaryPublicKey = BIP32::extended_private_to_public(BIP32::build_key($this->primaryPrivateKey->tuple(), (string)$walletPath->keyIndexPath()));
        $result = $this->sdk->upgradeKeyIndex($this->identifier, $keyIndex, $primaryPublicKey);

        $this->primaryPublicKeys[$keyIndex] = BIP32Key::create($primaryPublicKey);

        $this->keyIndex = $keyIndex;
        $this->walletPath = $walletPath;

        // update the blocktrail public keys
        foreach ($result['blocktrail_public_keys'] as $keyIndex => $pubKey) {
            if (!isset($this->blocktrailPublicKeys[$keyIndex])) {
                $this->blocktrailPublicKeys[$keyIndex] = BIP32Key::create($pubKey);
            }
        }

        return true;
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
            $this->pubKeys[(string)$path] = $this->primaryPublicKeys[$path->getKeyIndex()]->buildKey($path);
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
            list($address, ) = $this->getRedeemScriptByPath($path);

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
            $key = $this->primaryPublicKeys[$path->getKeyIndex()]->buildKey($path);
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
            BlocktrailSDK::sortMultisigKeys([
                $key->buildKey($path)->publicKey(),
                $this->backupPublicKey->buildKey($path->unhardenedPath())->publicKey(),
                $blocktrailPublicKey->buildKey($path)->publicKey()
            ])
        );

        return [$multiSig['address'], $multiSig['redeemScript']];
    }

    /**
     * get the path (and redeemScript) to specified address
     *
     * @param string $address
     * @return array
     */
    public function getPathForAddress($address) {
        return $this->sdk->getPathForAddress($this->identifier, $address);
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
     * @param array    $outputs             [address => value, ] or [[address, value], ] or [['address' => address, 'value' => value], ] coins to send
     *                                      value should be INT
     * @param string   $changeAddress       change address to use (autogenerated if NULL)
     * @param bool     $allowZeroConf
     * @param bool     $randomizeChangeIdx  randomize the location of the change (for increased privacy / anonimity)
     * @param string   $feeStrategy
     * @param null|int $forceFee            set a fixed fee instead of automatically calculating the correct fee, not recommended!
     * @return string the txid / transaction hash
     * @throws \Exception
     */
    public function pay(array $outputs, $changeAddress = null, $allowZeroConf = false, $randomizeChangeIdx = true, $feeStrategy = self::FEE_STRATEGY_OPTIMAL, $forceFee = null) {
        if ($this->locked) {
            throw new \Exception("Wallet needs to be unlocked to pay");
        }

        $outputs = self::normalizeOutputsStruct($outputs);

        $txBuilder = new TransactionBuilder();
        $txBuilder->randomizeChangeOutput($randomizeChangeIdx);
        $txBuilder->setFeeStrategy($feeStrategy);

        foreach ($outputs as $output) {
            $txBuilder->addRecipient($output['address'], $output['value']);
        }

        $this->coinSelectionForTxBuilder($txBuilder, true, $allowZeroConf, $forceFee);

        $apiCheckFee = $forceFee === null;

        return $this->sendTx($txBuilder, $apiCheckFee);
    }

    /**
     * parse outputs into normalized struct
     *
     * @param array $outputs    [address => value, ] or [[address, value], ] or [['address' => address, 'value' => value], ]
     * @return array            [['address' => address, 'value' => value], ]
     */
    public static function normalizeOutputsStruct(array $outputs) {
        $result = [];

        foreach ($outputs as $k => $v) {
            if (is_numeric($k)) {
                if (!is_array($v)) {
                    throw new \InvalidArgumentException("outputs should be [address => value, ] or [[address, value], ] or [['address' => address, 'value' => value], ]");
                }

                if (isset($v['address']) && isset($v['value'])) {
                    $address = $v['address'];
                    $value = $v['value'];
                } else if (count($v) == 2 && isset($v[0]) && isset($v[1])) {
                    $address = $v[0];
                    $value = $v[1];
                } else {
                    throw new \InvalidArgumentException("outputs should be [address => value, ] or [[address, value], ] or [['address' => address, 'value' => value], ]");
                }
            } else {
                $address = $k;
                $value = $v;
            }

            $result[] = ['address' => $address, 'value' => $value];
        }

        return $result;
    }

    /**
     * 'fund' the txBuilder with UTXOs (modified in place)
     *
     * @param TransactionBuilder    $txBuilder
     * @param bool|true             $lockUTXOs
     * @param bool|false            $allowZeroConf
     * @param null|int              $forceFee
     * @return TransactionBuilder
     */
    public function coinSelectionForTxBuilder(TransactionBuilder $txBuilder, $lockUTXOs = true, $allowZeroConf = false, $forceFee = null) {
        // get the data we should use for this transaction
        $coinSelection = $this->coinSelection($txBuilder->getOutputs(), $lockUTXOs, $allowZeroConf, $txBuilder->getFeeStrategy(), $forceFee);
        $utxos = $coinSelection['utxos'];
        $fee = $coinSelection['fee'];
        $change = $coinSelection['change'];

        if ($forceFee !== null) {
            $txBuilder->setFee($forceFee);
        } else {
            $txBuilder->validateFee($fee);
        }

        foreach ($utxos as $utxo) {
            $txBuilder->spendOutput($utxo['hash'], $utxo['idx'], $utxo['value'], $utxo['address'], $utxo['scriptpubkey_hex'], $utxo['path'], $utxo['redeem_script']);
        }

        return $txBuilder;
    }

    /**
     * build inputs and outputs lists for TransactionBuilder
     *
     * @param TransactionBuilder $txBuilder
     * @return array
     * @throws \Exception
     */
    public function buildTx(TransactionBuilder $txBuilder) {
        $send = $txBuilder->getOutputs();

        $utxos = $txBuilder->getUtxos();

        foreach ($utxos as $utxo) {
            if (!$utxo->address || !$utxo->value || !$utxo->scriptPubKeyHex) {
                $tx = $this->sdk->transaction($utxo->hash);

                if (!$tx || !isset($tx['outputs'][$utxo->index])) {
                    throw new \Exception("Invalid output [{$utxo->hash}][{$utxo->index}]");
                }

                $output = $tx['outputs'][$utxo->index];

                if (!$utxo->address) {
                    $utxo->address = $output['address'];
                }
                if (!$utxo->value) {
                    $utxo->value = $output['value'];
                }
                if (!$utxo->scriptPubKeyHex) {
                    $utxo->scriptPubKeyHex = $output['script_hex'];
                }
            }

            if (!$utxo->path) {
                $address = $utxo->address;
                if (!BitcoinLib::validate_address($address)) {
                    throw new \Exception("Invalid address [{$address}]");
                }

                $utxo->path = $this->getPathForAddress($address);
            }

            if (!$utxo->redeemScript) {
                list(, $redeemScript) = $this->getRedeemScriptByPath($utxo->path);
                $utxo->redeemScript = $redeemScript;
            }
        }

        if (array_sum(array_map(function (UTXO $utxo) { return $utxo->value; }, $utxos)) < array_sum(array_column($send, 'value'))) {
            throw new \Exception("Atempting to spend more than sum of UTXOs");
        }

        list($fee, $change) = $this->determineFeeAndChange($txBuilder, $this->getOptimalFeePerKB(), $this->getLowPriorityFeePerKB());

        if ($txBuilder->getValidateFee() !== null) {
            if (abs($txBuilder->getValidateFee() - $fee) > Wallet::BASE_FEE) {
                throw new \Exception("the fee suggested by the coin selection ({$txBuilder->getValidateFee()}) seems incorrect ({$fee})");
            }
        }

        if ($change > 0) {
            $send[] = [
                'address' => $txBuilder->getChangeAddress() ?: $this->getNewAddress(),
                'value' => $change
            ];
        }

        // create raw transaction
        $inputs = array_map(function (UTXO $utxo) {
            return [
                'txid' => $utxo->hash,
                'vout' => (int)$utxo->index,
                'address' => $utxo->address,
                'scriptPubKey' => $utxo->scriptPubKeyHex,
                'value' => $utxo->value,
                'path' => $utxo->path,
                'redeemScript' => $utxo->redeemScript
            ];
        }, $utxos);


        // outputs should be randomized to make the change harder to detect
        if ($txBuilder->shouldRandomizeChangeOuput()) {
            shuffle($send);
        }

        return [$inputs, $send];
    }

    public function determineFeeAndChange(TransactionBuilder $txBuilder, $optimalFeePerKB, $lowPriorityFeePerKB) {
        $send = $txBuilder->getOutputs();
        $utxos = $txBuilder->getUtxos();

        $fee = $txBuilder->getFee();
        $change = null;

        // if the fee is fixed we just need to calculate the change
        if ($fee !== null) {
            $change = $this->determineChange($utxos, $send, $fee);

            // if change is not dust we need to add a change output
            if ($change > Blocktrail::DUST) {
                $send[] = ['address' => 'change', 'value' => $change];
            } else {
                // if change is dust we do nothing (implicitly it's added to the fee)
                $change = 0;
            }
        } else {
            $fee = $this->determineFee($utxos, $send, $txBuilder->getFeeStrategy(), $optimalFeePerKB, $lowPriorityFeePerKB);

            $change = $this->determineChange($utxos, $send, $fee);

            if ($change > 0) {
                $changeIdx = count($send);
                // set dummy change output
                $send[$changeIdx] = ['address' => 'change', 'value' => $change];

                // recaculate fee now that we know that we have a change output
                $fee2 = $this->determineFee($utxos, $send, $txBuilder->getFeeStrategy(), $optimalFeePerKB, $lowPriorityFeePerKB);

                // unset dummy change output
                unset($send[$changeIdx]);

                // if adding the change output made the fee bump up and the change is smaller than the fee
                //  then we're not doing change
                if ($fee2 > $fee && $fee2 > $change) {
                    $change = 0;
                } else {
                    $change = $this->determineChange($utxos, $send, $fee2);

                    // if change is not dust we need to add a change output
                    if ($change > Blocktrail::DUST) {
                        $send[$changeIdx] = ['address' => 'change', 'value' => $change];
                    } else {
                        // if change is dust we do nothing (implicitly it's added to the fee)
                        $change = 0;
                    }
                }
            }
        }

        $fee = $this->determineFee($utxos, $send, $txBuilder->getFeeStrategy(), $optimalFeePerKB, $lowPriorityFeePerKB);

        return [$fee, $change];
    }

    /**
     * create, sign and send transction based on TransactionBuilder
     *
     * @param TransactionBuilder $txBuilder
     * @param bool $apiCheckFee     let the API check if the fee is correct
     * @return string
     * @throws \Exception
     */
    public function sendTx(TransactionBuilder $txBuilder, $apiCheckFee = true) {
        list($inputs, $outputs) = $this->buildTx($txBuilder);

        return $this->_sendTx($inputs, $outputs, $apiCheckFee);
    }

    /**
     * !! INTERNAL METHOD, public for testing purposes !!
     * create, sign and send transction based on inputs and outputs
     *
     * @param      $inputs
     * @param      $outputs
     * @param bool $apiCheckFee     let the API check if the fee is correct
     * @return string
     * @throws \Exception
     * @internal
     */
    public function _sendTx($inputs, $outputs, $apiCheckFee = true) {
        if ($this->locked) {
            throw new \Exception("Wallet needs to be unlocked to pay");
        }

        // create raw unsigned TX
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
        $finished = $this->sendTransaction($signed['hex'], array_column($inputs, 'path'), $apiCheckFee);

        return $finished;
    }

    /**
     * only supports estimating fee for 2of3 multsig UTXOs and P2PKH/P2SH outputs
     *
     * @param int $utxoCnt      number of unspent inputs in transaction
     * @param int $outputCnt    number of outputs in transaction
     * @return float
     * @access public           reminder that people might use this!
     */
    public static function estimateFee($utxoCnt, $outputCnt) {
        $size = self::estimateSize(self::estimateSizeUTXOs($utxoCnt), self::estimateSizeOutputs($outputCnt));

        return self::baseFeeForSize($size);
    }

    /**
     * @param int $size     size in bytes
     * @return int          fee in satoshi
     */
    public static function baseFeeForSize($size) {
        $sizeKB = (int)ceil($size / 1000);

        return $sizeKB * self::BASE_FEE;
    }

    /**
     * @param int $txinSize
     * @param int $txoutSize
     * @return float
     */
    public static function estimateSize($txinSize, $txoutSize) {
        return 4 + 4 + $txinSize + 4 + $txoutSize + 4; // version + txinVarInt + txin + txoutVarInt + txout + locktime
    }

    /**
     * only supports estimating size for P2PKH/P2SH outputs
     *
     * @param int $outputCnt    number of outputs in transaction
     * @return float
     */
    public static function estimateSizeOutputs($outputCnt) {
        return ($outputCnt * 34);
    }

    /**
     * only supports estimating size for 2of3 multsig UTXOs
     *
     * @param int $utxoCnt      number of unspent inputs in transaction
     * @return float
     */
    public static function estimateSizeUTXOs($utxoCnt) {
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
                    3, // scriptVarInt[>=253]
                    ((1 + 72) * $sigCnt), // (OP_PUSHDATA[<75] + 72) * sigCnt
                    (2 + 105) + // OP_PUSHDATA[>=75] + script
                    4, // sequence
                ]);
            } else {
                $txinSize += array_sum([
                    32, // txhash
                    4, // idx
                    73, // sig
                    34, // script
                    4, // sequence
                ]);
            }
        }

        return $txinSize;
    }

    /**
     * determine how much fee is required based on the inputs and outputs
     *  this is an estimation, not a proper 100% correct calculation
     *
     * @param UTXO[]  $utxos
     * @param array[] $outputs
     * @param         $feeStrategy
     * @param         $optimalFeePerKB
     * @param         $lowPriorityFeePerKB
     * @return int
     * @throws BlocktrailSDKException
     */
    protected function determineFee($utxos, $outputs, $feeStrategy, $optimalFeePerKB, $lowPriorityFeePerKB) {
        $outputSize = 0;
        foreach ($outputs as $output) {
            if (isset($output['scriptPubKey'])) {
                $outputSize += strlen($output['scriptPubKey']) / 2; // asume HEX
            } else {
                $outputSize += 34;
            }
        }

        $size = self::estimateSize(self::estimateSizeUTXOs(count($utxos)), $outputSize);

        switch ($feeStrategy) {
            case self::FEE_STRATEGY_BASE_FEE:
                return self::baseFeeForSize($size);

            case self::FEE_STRATEGY_OPTIMAL:
                return (int)round(($size / 1000) * $optimalFeePerKB);

            case self::FEE_STRATEGY_LOW_PRIORITY:
                return (int)round(($size / 1000) * $lowPriorityFeePerKB);

            default:
                throw new BlocktrailSDKException("Unknown feeStrategy [{$feeStrategy}]");
        }
    }

    /**
     * determine how much change is left over based on the inputs and outputs and the fee
     *
     * @param UTXO[]    $utxos
     * @param array[]   $outputs
     * @param int       $fee
     * @return int
     */
    protected function determineChange($utxos, $outputs, $fee) {
        $inputsTotal = array_sum(array_map(function (UTXO $utxo) {
            return $utxo->value;
        }, $utxos));
        $outputsTotal = array_sum(array_column($outputs, 'value'));

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
     * @param array[]  $outputs
     * @param bool     $lockUTXO
     * @param bool     $allowZeroConf
     * @param string   $feeStrategy
     * @param null|int $forceFee
     * @return array
     */
    public function coinSelection($outputs, $lockUTXO = true, $allowZeroConf = false, $feeStrategy = self::FEE_STRATEGY_OPTIMAL, $forceFee = null) {
        $result = $this->sdk->coinSelection($this->identifier, $outputs, $lockUTXO, $allowZeroConf, $feeStrategy, $forceFee);

        $this->optimalFeePerKB = $result['fees'][self::FEE_STRATEGY_OPTIMAL];
        $this->lowPriorityFeePerKB = $result['fees'][self::FEE_STRATEGY_LOW_PRIORITY];
        $this->feePerKBAge = time();

        return $result;
    }

    public function getOptimalFeePerKB() {
        if (!$this->optimalFeePerKB || $this->feePerKBAge < time() - 60) {
            $this->updateFeePerKB();
        }

        return $this->optimalFeePerKB;
    }

    public function getLowPriorityFeePerKB() {
        if (!$this->lowPriorityFeePerKB || $this->feePerKBAge < time() - 60) {
            $this->updateFeePerKB();
        }

        return $this->lowPriorityFeePerKB;
    }

    public function updateFeePerKB() {
        $result = $this->sdk->feePerKB();

        $this->optimalFeePerKB = $result[self::FEE_STRATEGY_OPTIMAL];
        $this->lowPriorityFeePerKB = $result[self::FEE_STRATEGY_LOW_PRIORITY];

        $this->feePerKBAge = time();
    }

    /**
     * delete the wallet
     *
     * @param bool $force ignore warnings (such as non-zero balance)
     * @return mixed
     * @throws \Exception
     */
    public function deleteWallet($force = false) {
        if ($this->locked) {
            throw new \Exception("Wallet needs to be unlocked to delete wallet");
        }

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
     * lock a specific unspent output
     *
     * @param     $txHash
     * @param     $txIdx
     * @param int $ttl
     * @return bool
     */
    public function lockUTXO($txHash, $txIdx, $ttl = 3) {
        return $this->sdk->lockWalletUTXO($this->identifier, $txHash, $txIdx, $ttl);
    }

    /**
     * unlock a specific unspent output
     *
     * @param     $txHash
     * @param     $txIdx
     * @return bool
     */
    public function unlockUTXO($txHash, $txIdx) {
        return $this->sdk->unlockWalletUTXO($this->identifier, $txHash, $txIdx);
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
