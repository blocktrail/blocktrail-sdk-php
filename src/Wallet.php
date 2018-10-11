<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Address\Base58AddressInterface;
use BitWasp\Bitcoin\Address\PayToPubKeyHashAddress;
use BitWasp\Bitcoin\Address\ScriptHashAddress;
use BitWasp\Bitcoin\Bitcoin;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKeyFactory;
use BitWasp\Bitcoin\MessageSigner\MessageSigner;
use BitWasp\Bitcoin\Script\P2shScript;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Script\ScriptType;
use BitWasp\Bitcoin\Script\WitnessScript;
use BitWasp\Bitcoin\Transaction\Factory\SignData;
use BitWasp\Bitcoin\Transaction\Factory\Signer;
use BitWasp\Bitcoin\Transaction\Factory\TxBuilder;
use BitWasp\Bitcoin\Transaction\OutPoint;
use BitWasp\Bitcoin\Transaction\SignatureHash\SigHash;
use BitWasp\Bitcoin\Transaction\Transaction;
use BitWasp\Bitcoin\Transaction\TransactionInterface;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\Address\AddressReaderBase;
use Blocktrail\SDK\Address\CashAddress;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Bitcoin\BIP32Path;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

/**
 * Class Wallet
 */
abstract class Wallet implements WalletInterface {

    const WALLET_VERSION_V1 = 'v1';
    const WALLET_VERSION_V2 = 'v2';
    const WALLET_VERSION_V3 = 'v3';

    const CHAIN_BTC_DEFAULT = 0;
    const CHAIN_BCC_DEFAULT = 1;
    const CHAIN_BTC_SEGWIT = 2;

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
     * @var string
     */
    protected $checksum;

    /**
     * @var bool
     */
    protected $locked = true;

    /**
     * @var bool
     */
    protected $isSegwit = false;

    /**
     * @var int
     */
    protected $chainIndex;

    /**
     * @var int
     */
    protected $changeIndex;

    /**
     * @var AddressReaderBase
     */
    protected $addressReader;

    protected $highPriorityFeePerKB;
    protected $optimalFeePerKB;
    protected $lowPriorityFeePerKB;
    protected $feePerKBAge;
    protected $allowedSignModes = [SignInfo::MODE_DONTSIGN, SignInfo::MODE_SIGN];

    /**
     * @param BlocktrailSDKInterface        $sdk                        SDK instance used to do requests
     * @param string                        $identifier                 identifier of the wallet
     * @param BIP32Key[]                    $primaryPublicKeys
     * @param BIP32Key                      $backupPublicKey            should be BIP32 master public key M/
     * @param BIP32Key[]                    $blocktrailPublicKeys
     * @param int                           $keyIndex
     * @param string                        $network
     * @param bool                          $testnet
     * @param bool                          $segwit
     * @param string                        $checksum
     * @throws BlocktrailSDKException
     */
    public function __construct(BlocktrailSDKInterface $sdk, $identifier, array $primaryPublicKeys, $backupPublicKey, array $blocktrailPublicKeys, $keyIndex, $network, $testnet, $segwit, AddressReaderBase $addressReader, $checksum) {
        $this->sdk = $sdk;

        $this->identifier = $identifier;
        $this->backupPublicKey = BlocktrailSDK::normalizeBIP32Key($backupPublicKey);
        $this->primaryPublicKeys = BlocktrailSDK::normalizeBIP32KeyArray($primaryPublicKeys);
        $this->blocktrailPublicKeys = BlocktrailSDK::normalizeBIP32KeyArray($blocktrailPublicKeys);

        $this->network = $network;
        $this->testnet = $testnet;
        $this->keyIndex = $keyIndex;
        $this->checksum = $checksum;

        if ($network === "bitcoin") {
            if ($segwit) {
                $chainIdx = self::CHAIN_BTC_DEFAULT;
                $changeIdx = self::CHAIN_BTC_SEGWIT;
            } else {
                $chainIdx = self::CHAIN_BTC_DEFAULT;
                $changeIdx = self::CHAIN_BTC_DEFAULT;
            }
        } else {
            if ($segwit && $network === "bitcoincash") {
                throw new BlocktrailSDKException("Received segwit flag for bitcoincash - abort");
            }
            $chainIdx = self::CHAIN_BCC_DEFAULT;
            $changeIdx = self::CHAIN_BCC_DEFAULT;
        }

        $this->addressReader = $addressReader;
        $this->isSegwit = (bool) $segwit;
        $this->chainIndex = $chainIdx;
        $this->changeIndex = $changeIdx;
    }

    /**
     * @return AddressReaderBase
     */
    public function getAddressReader() {
        return $this->addressReader;
    }

    /**
     * @param int|null $chainIndex
     * @return WalletPath
     * @throws BlocktrailSDKException
     */
    protected function getWalletPath($chainIndex = null) {
        if ($chainIndex === null) {
            return WalletPath::create($this->keyIndex, $this->chainIndex);
        } else {
            if (!is_int($chainIndex)) {
                throw new BlocktrailSDKException("Chain index is invalid - should be an integer");
            }
            return WalletPath::create($this->keyIndex, $chainIndex);
        }
    }

    /**
     * @return bool
     */
    public function isSegwit() {
        return $this->isSegwit;
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
     * Returns the wallets backup public key
     *
     * @return [xpub, path]
     */
    public function getBackupKey() {
        return $this->backupPublicKey->tuple();
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
        $primaryPublicKey = $this->primaryPrivateKey->buildKey((string)$walletPath->keyIndexPath()->publicPath());

        // $primaryPublicKey = BIP32::extended_private_to_public(BIP32::build_key($this->primaryPrivateKey->tuple(), (string)$walletPath->keyIndexPath()));
        $result = $this->sdk->upgradeKeyIndex($this->identifier, $keyIndex, $primaryPublicKey->tuple());

        $this->primaryPublicKeys[$keyIndex] = $primaryPublicKey;
        $this->keyIndex = $keyIndex;

        // update the blocktrail public keys
        foreach ($result['blocktrail_public_keys'] as $keyIndex => $pubKey) {
            if (!isset($this->blocktrailPublicKeys[$keyIndex])) {
                $path = $pubKey[1];
                $pubKey = $pubKey[0];
                $this->blocktrailPublicKeys[$keyIndex] = BIP32Key::create(HierarchicalKeyFactory::fromExtended($pubKey), $path);
            }
        }

        return true;
    }

    /**
     * get a new BIP32 derivation for the next (unused) address
     *  by requesting it from the API
     *
     * @return string
     * @param int|null $chainIndex
     * @throws \Exception
     */
    protected function getNewDerivation($chainIndex = null) {
        $path = $this->getWalletPath($chainIndex)->path()->last("*");

        if (self::VERIFY_NEW_DERIVATION) {
            $new = $this->sdk->_getNewDerivation($this->identifier, (string)$path);

            $path = $new['path'];
            $address = $new['address'];

            $serverDecoded = $this->addressReader->fromString($address);

            $redeemScript = $new['redeem_script'];
            $witnessScript = array_key_exists('witness_script', $new) ? $new['witness_script'] : null;

            /** @var ScriptInterface $checkRedeemScript */
            /** @var ScriptInterface $checkWitnessScript */
            list($checkAddress, $checkRedeemScript, $checkWitnessScript) = $this->getRedeemScriptByPath($path);

            $oursDecoded = $this->addressReader->fromString($checkAddress);

            if ($this->network === "bitcoincash" &&
                $serverDecoded instanceof Base58AddressInterface &&
                $oursDecoded instanceof CashAddress
            ) {
                // our address is a cashaddr, server gave us base58.

                if (!$oursDecoded->getHash()->equals($serverDecoded->getHash())) {
                    throw new BlocktrailSDKException("Failed to verify legacy address from server [hash mismatch]");
                }

                $matchedP2PKH = $serverDecoded instanceof PayToPubKeyHashAddress && $oursDecoded->getType() === ScriptType::P2PKH;
                $matchedP2SH = $serverDecoded instanceof ScriptHashAddress && $oursDecoded->getType() === ScriptType::P2SH;
                if (!($matchedP2PKH || $matchedP2SH)) {
                    throw new BlocktrailSDKException("Failed to verify legacy address from server [prefix mismatch]");
                }

                // promote the legacy address to our cashaddr, as they are equivalent.
                $address = $checkAddress;
            }

            if ($checkAddress != $address) {
                throw new \Exception("Failed to verify that address from API [{$address}] matches address locally [{$checkAddress}]");
            }

            if ($checkRedeemScript && $checkRedeemScript->getHex() != $redeemScript) {
                throw new \Exception("Failed to verify that redeemScript from API [{$redeemScript}] matches address locally [{$checkRedeemScript->getHex()}]");
            }

            if ($checkWitnessScript && $checkWitnessScript->getHex() != $witnessScript) {
                throw new \Exception("Failed to verify that witnessScript from API [{$witnessScript}] matches address locally [{$checkWitnessScript->getHex()}]");
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
     * @param string $path
     * @return WalletScript
     */
    public function getWalletScriptByPath($path) {
        $path = BIP32Path::path($path);
        if ($pubKey = $this->getParentPublicKey($path)) {
            $key = $pubKey->buildKey($path->publicPath());
        } else {
            $key = $this->primaryPublicKeys[$path->getKeyIndex()]->buildKey($path);
        }

        return $this->getWalletScriptFromKey($key, $path);
    }

    /**
     * get address and redeemScript for specified path
     *
     * @param string    $path
     * @return array[string, ScriptInterface, ScriptInterface|null]     [address, redeemScript, witnessScript]
     */
    public function getRedeemScriptByPath($path) {
        $walletScript = $this->getWalletScriptByPath($path);

        $redeemScript = $walletScript->isP2SH() ? $walletScript->getRedeemScript() : null;
        $witnessScript = $walletScript->isP2WSH() ? $walletScript->getWitnessScript() : null;
        return [$walletScript->getAddress()->getAddress(), $redeemScript, $witnessScript];
    }

    /**
     * @param BIP32Key          $key
     * @param string|BIP32Path  $path
     * @return string
     */
    protected function getAddressFromKey(BIP32Key $key, $path) {
        return $this->getWalletScriptFromKey($key, $path)->getAddress()->getAddress();
    }

    /**
     * @param BIP32Key          $key
     * @param string|BIP32Path  $path
     * @return WalletScript
     * @throws \Exception
     */
    protected function getWalletScriptFromKey(BIP32Key $key, $path) {
        $path = BIP32Path::path($path)->publicPath();

        $blocktrailPublicKey = $this->getBlocktrailPublicKey($path);

        $multisig = ScriptFactory::scriptPubKey()->multisig(2, BlocktrailSDK::sortMultisigKeys([
            $key->buildKey($path)->publicKey(),
            $this->backupPublicKey->buildKey($path->unhardenedPath())->publicKey(),
            $blocktrailPublicKey->buildKey($path)->publicKey()
        ]), false);

        $type = (int)$key->path()[2];
        if ($this->isSegwit && $type === Wallet::CHAIN_BTC_SEGWIT) {
            $witnessScript = new WitnessScript($multisig);
            $redeemScript = new P2shScript($witnessScript);
            $scriptPubKey = $redeemScript->getOutputScript();
        } else if ($type === Wallet::CHAIN_BTC_DEFAULT || $type === Wallet::CHAIN_BCC_DEFAULT) {
            $witnessScript = null;
            $redeemScript = new P2shScript($multisig);
            $scriptPubKey = $redeemScript->getOutputScript();
        } else {
            throw new BlocktrailSDKException("Unsupported chain in path");
        }

        $address = $this->addressReader->fromOutputScript($scriptPubKey);

        return new WalletScript($path, $scriptPubKey, $redeemScript, $witnessScript, $address);
    }

    /**
     * get the path (and redeemScript) to specified address
     *
     * @param string $address
     * @return array
     */
    public function getPathForAddress($address) {
        $decoded = $this->addressReader->fromString($address);
        if ($decoded instanceof CashAddress) {
            $address = $decoded->getLegacyAddress();
        }

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
     * @param int|null $chainIndex
     * @return string[]     [path, address]
     */
    public function getNewAddressPair($chainIndex = null) {
        $path = $this->getNewDerivation($chainIndex);
        $address = $this->getAddressByPath($path);

        return [$path, $address];
    }

    /**
     * generate a new derived private key and return the new address for it
     *
     * @param int|null $chainIndex
     * @return string
     */
    public function getNewAddress($chainIndex = null) {
        return $this->getNewAddressPair($chainIndex)[1];
    }

    /**
     * generate a new derived private key and return the new address for it
     *
     * @return string
     */
    public function getNewChangeAddress() {
        return $this->getNewAddressPair($this->changeIndex)[1];
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
     * create, sign and send a transaction
     *
     * @param array    $outputs             [address => value, ] or [[address, value], ] or [['address' => address, 'value' => value], ] coins to send
     *                                      value should be INT
     * @param string   $changeAddress       change address to use (autogenerated if NULL)
     * @param bool     $allowZeroConf
     * @param bool     $randomizeChangeIdx  randomize the location of the change (for increased privacy / anonimity)
     * @param string   $feeStrategy
     * @param null|int $forceFee            set a fixed fee instead of automatically calculating the correct fee, not recommended!
     * @param bool     $apiCheckFee         let the API apply sanity checks to the fee
     * @return string the txid / transaction hash
     * @throws \Exception
     */
    public function pay(array $outputs, $changeAddress = null, $allowZeroConf = false, $randomizeChangeIdx = true, $feeStrategy = self::FEE_STRATEGY_OPTIMAL, $forceFee = null, $apiCheckFee = true) {
        if ($this->locked) {
            throw new \Exception("Wallet needs to be unlocked to pay");
        }

        if ($forceFee && $feeStrategy !== self::FEE_STRATEGY_FORCE_FEE) {
            throw new \InvalidArgumentException("feeStrategy should be set to force_fee to set a forced fee");
        }

        $outputs = (new OutputsNormalizer($this->getAddressReader()))->normalize($outputs);

        $txBuilder = new TransactionBuilder($this->addressReader);
        $txBuilder->randomizeChangeOutput($randomizeChangeIdx);
        $txBuilder->setFeeStrategy($feeStrategy);
        $txBuilder->setChangeAddress($changeAddress);

        foreach ($outputs as $output) {
            $txBuilder->addOutput($output);
        }

        $this->coinSelectionForTxBuilder($txBuilder, true, $allowZeroConf, $forceFee);

        if ($forceFee !== null) {
            $apiCheckFee = true;
        }

        return $this->sendTx($txBuilder, $apiCheckFee);
    }

    /**
     * determine max spendable from wallet after fees
     *
     * @param bool     $allowZeroConf
     * @param string   $feeStrategy
     * @param null|int $forceFee set a fixed fee instead of automatically calculating the correct fee, not recommended!
     * @param int      $outputCnt
     * @return string
     * @throws BlocktrailSDKException
     */
    public function getMaxSpendable($allowZeroConf = false, $feeStrategy = self::FEE_STRATEGY_OPTIMAL, $forceFee = null, $outputCnt = 1) {
        return $this->sdk->walletMaxSpendable($this->identifier, $allowZeroConf, $feeStrategy, $forceFee, $outputCnt);
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
                } elseif (count($v) == 2 && isset($v[0]) && isset($v[1])) {
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
        $coinSelection = $this->coinSelection($txBuilder->getOutputs(/* $json = */true), $lockUTXOs, $allowZeroConf, $txBuilder->getFeeStrategy(), $forceFee);
        
        $utxos = $coinSelection['utxos'];
        $fee = $coinSelection['fee'];
        $change = $coinSelection['change'];

        if ($forceFee !== null) {
            $txBuilder->setFee($forceFee);
        } else {
            $txBuilder->validateFee($fee);
        }

        foreach ($utxos as $utxo) {
            $signMode = SignInfo::MODE_SIGN;
            if (isset($utxo['sign_mode'])) {
                $signMode = $utxo['sign_mode'];
                if (!in_array($signMode, $this->allowedSignModes)) {
                    throw new \Exception("Sign mode disallowed by wallet");
                }
            }

            $txBuilder->spendOutput($utxo['hash'], $utxo['idx'], $utxo['value'], $utxo['address'], $utxo['scriptpubkey_hex'], $utxo['path'], $utxo['redeem_script'], $utxo['witness_script'], $signMode);
        }

        return $txBuilder;
    }

    /**
     * build inputs and outputs lists for TransactionBuilder
     *
     * @param TransactionBuilder $txBuilder
     * @return [TransactionInterface, SignInfo[]]
     * @throws \Exception
     */
    public function buildTx(TransactionBuilder $txBuilder) {
        $send = $txBuilder->getOutputs();
        $utxos = $txBuilder->getUtxos();
        $signInfo = [];

        $txb = new TxBuilder();

        foreach ($utxos as $utxo) {
            if (!$utxo->address || !$utxo->value || !$utxo->scriptPubKey) {
                $tx = $this->sdk->transaction($utxo->hash);

                if (!$tx || !isset($tx['outputs'][$utxo->index])) {
                    throw new \Exception("Invalid output [{$utxo->hash}][{$utxo->index}]");
                }

                $output = $tx['outputs'][$utxo->index];

                if (!$utxo->address) {
                    $utxo->address = $this->addressReader->fromString($output['address']);
                }
                if (!$utxo->value) {
                    $utxo->value = $output['value'];
                }
                if (!$utxo->scriptPubKey) {
                    $utxo->scriptPubKey = ScriptFactory::fromHex($output['script_hex']);
                }
            }

            if (SignInfo::MODE_SIGN === $utxo->signMode) {
                if (!$utxo->path) {
                    $utxo->path = $this->getPathForAddress($utxo->address->getAddress());
                }

                if (!$utxo->redeemScript || !$utxo->witnessScript) {
                    list(, $redeemScript, $witnessScript) = $this->getRedeemScriptByPath($utxo->path);
                    $utxo->redeemScript = $redeemScript;
                    $utxo->witnessScript = $witnessScript;
                }
            }

            $signInfo[] = $utxo->getSignInfo();
        }

        $utxoSum = array_sum(array_map(function (UTXO $utxo) {
            return $utxo->value;
        }, $utxos));
        if ($utxoSum < array_sum(array_column($send, 'value'))) {
            throw new \Exception("Atempting to spend more than sum of UTXOs");
        }

        list($fee, $change) = $this->determineFeeAndChange($txBuilder, $this->getHighPriorityFeePerKB(), $this->getOptimalFeePerKB(), $this->getLowPriorityFeePerKB());

        if ($txBuilder->getValidateFee() !== null) {
            // sanity check to make sure the API isn't giving us crappy data
            if (abs($txBuilder->getValidateFee() - $fee) > (Wallet::BASE_FEE * 5)) {
                throw new \Exception("the fee suggested by the coin selection ({$txBuilder->getValidateFee()}) seems incorrect ({$fee})");
            }
        }

        if ($change > 0) {
            $send[] = [
                'address' => $txBuilder->getChangeAddress() ?: $this->getNewChangeAddress(),
                'value' => $change
            ];
        }

        foreach ($utxos as $utxo) {
            $txb->spendOutPoint(new OutPoint(Buffer::hex($utxo->hash), $utxo->index));
        }

        // outputs should be randomized to make the change harder to detect
        if ($txBuilder->shouldRandomizeChangeOuput()) {
            $this->sdk->shuffle($send);
        }

        foreach ($send as $out) {
            assert(isset($out['value']));

            if (isset($out['scriptPubKey'])) {
                $txb->output($out['value'], $out['scriptPubKey']);
            } elseif (isset($out['address'])) {
                $txb->output($out['value'], $this->addressReader->fromString($out['address'])->getScriptPubKey());
            } else {
                throw new \Exception();
            }
        }

        return [$txb->get(), $signInfo];
    }

    public function determineFeeAndChange(TransactionBuilder $txBuilder, $highPriorityFeePerKB, $optimalFeePerKB, $lowPriorityFeePerKB) {
        $send = (new OutputsNormalizer($this->addressReader))->normalize($txBuilder->getOutputs());
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
                // if change is dust we add it to the fee
                $fee += $change;
                $change = 0;
            }

            return [$fee, $change];
        } else {
            $fee = $this->determineFee($utxos, $send, $txBuilder->getFeeStrategy(), $highPriorityFeePerKB, $optimalFeePerKB, $lowPriorityFeePerKB);

            $change = $this->determineChange($utxos, $send, $fee);

            if ($change > 0) {
                $changeIdx = count($send);
                // set dummy change output
                $send[$changeIdx] = ['address' => 'change', 'value' => $change];

                // recaculate fee now that we know that we have a change output
                $fee2 = $this->determineFee($utxos, $send, $txBuilder->getFeeStrategy(), $highPriorityFeePerKB, $optimalFeePerKB, $lowPriorityFeePerKB);

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


            $fee = $this->determineFee($utxos, $send, $txBuilder->getFeeStrategy(), $highPriorityFeePerKB, $optimalFeePerKB, $lowPriorityFeePerKB);

            return [$fee, $change];
        }
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
        list($tx, $signInfo) = $this->buildTx($txBuilder);

        return $this->_sendTx($tx, $signInfo, $apiCheckFee);
    }

    /**
     * !! INTERNAL METHOD, public for testing purposes !!
     * create, sign and send transction based on inputs and outputs
     *
     * @param Transaction $tx
     * @param SignInfo[]  $signInfo
     * @param bool $apiCheckFee     let the API check if the fee is correct
     * @return string
     * @throws \Exception
     * @internal
     */
    public function _sendTx(Transaction $tx, array $signInfo, $apiCheckFee = true) {
        if ($this->locked) {
            throw new \Exception("Wallet needs to be unlocked to pay");
        }

        assert(Util::all(function ($signInfo) {
            return $signInfo instanceof SignInfo;
        }, $signInfo), '$signInfo should be SignInfo[]');

        // sign the transaction with our keys
        $signed = $this->signTransaction($tx, $signInfo);

        $txs = [
            'signed_transaction' => $signed->getHex(),
            'base_transaction' => $signed->getBaseSerialization()->getHex(),
        ];

        // send the transaction
        return $this->sendTransaction($txs, array_map(function (SignInfo $r) {
            return (string)$r->path;
        }, $signInfo), $apiCheckFee);
    }

    /**
     * only supports estimating fee for 2of3 multsig UTXOs and P2PKH/P2SH outputs
     *
     * @todo: mark this as deprecated, insist on the utxo's or qualified scripts.
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
     * @todo: variable varint
     * @todo: deprecate
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
                    1, // OP_0
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
     * @param         $highPriorityFeePerKB
     * @param         $optimalFeePerKB
     * @param         $lowPriorityFeePerKB
     * @return int
     * @throws BlocktrailSDKException
     */
    protected function determineFee($utxos, $outputs, $feeStrategy, $highPriorityFeePerKB, $optimalFeePerKB, $lowPriorityFeePerKB) {

        $size = SizeEstimation::estimateVsize($utxos, $outputs);

        switch ($feeStrategy) {
            case self::FEE_STRATEGY_BASE_FEE:
                return self::baseFeeForSize($size);

            case self::FEE_STRATEGY_HIGH_PRIORITY:
                return (int)round(($size / 1000) * $highPriorityFeePerKB);

            case self::FEE_STRATEGY_OPTIMAL:
                return (int)round(($size / 1000) * $optimalFeePerKB);

            case self::FEE_STRATEGY_LOW_PRIORITY:
                return (int)round(($size / 1000) * $lowPriorityFeePerKB);

            case self::FEE_STRATEGY_FORCE_FEE:
                throw new BlocktrailSDKException("Can't determine when for force_fee");

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
     * @param Transaction $tx
     * @param SignInfo[]  $signInfo
     * @return TransactionInterface
     * @throws \Exception
     */
    protected function signTransaction(Transaction $tx, array $signInfo) {
        $signer = new Signer($tx, Bitcoin::getEcAdapter());

        assert(Util::all(function ($signInfo) {
            return $signInfo instanceof SignInfo;
        }, $signInfo), '$signInfo should be SignInfo[]');

        $sigHash = SigHash::ALL;
        if ($this->network === "bitcoincash") {
            $sigHash |= SigHash::BITCOINCASH;
            $signer->redeemBitcoinCash(true);
        }

        foreach ($signInfo as $idx => $info) {
            if ($info->mode === SignInfo::MODE_SIGN) {
                // required SignInfo: path, redeemScript|witnessScript, output
                $path = BIP32Path::path($info->path)->privatePath();
                $key = $this->primaryPrivateKey->buildKey($path)->key()->getPrivateKey();
                $signData = new SignData();
                if ($info->redeemScript) {
                    $signData->p2sh($info->redeemScript);
                }
                if ($info->witnessScript) {
                    $signData->p2wsh($info->witnessScript);
                }
                $input = $signer->input($idx, $info->output, $signData);
                $input->sign($key, $sigHash);
            }
        }

        return $signer->get();
    }

    /**
     * send the transaction using the API
     *
     * @param string|array  $signed
     * @param string[]      $paths
     * @param bool          $checkFee
     * @return string           the complete raw transaction
     * @throws \Exception
     */
    protected function sendTransaction($signed, $paths, $checkFee = false) {
        return $this->sdk->sendTransaction($this->identifier, $signed, $paths, $checkFee);
    }

    /**
     * @param \array[] $outputs
     * @param bool $lockUTXO
     * @param bool $allowZeroConf
     * @param int|null|string $feeStrategy
     * @param null $forceFee
     * @return array
     */
    public function coinSelection($outputs, $lockUTXO = true, $allowZeroConf = false, $feeStrategy = self::FEE_STRATEGY_OPTIMAL, $forceFee = null) {
        $send = [];
        foreach ((new OutputsNormalizer($this->addressReader))->normalize($outputs) as $output) {
            $send[] = [
                "value" => $output['value'],
                "scriptPubKey" => $output['scriptPubKey']->getHex(),
            ];
        }

        $result = $this->sdk->coinSelection($this->identifier, $send, $lockUTXO, $allowZeroConf, $feeStrategy, $forceFee);

        $this->highPriorityFeePerKB = $result['fees'][self::FEE_STRATEGY_HIGH_PRIORITY];
        $this->optimalFeePerKB = $result['fees'][self::FEE_STRATEGY_OPTIMAL];
        $this->lowPriorityFeePerKB = $result['fees'][self::FEE_STRATEGY_LOW_PRIORITY];
        $this->feePerKBAge = time();

        return $result;
    }

    public function getHighPriorityFeePerKB() {
        if (!$this->highPriorityFeePerKB || $this->feePerKBAge < time() - 60) {
            $this->updateFeePerKB();
        }

        return $this->highPriorityFeePerKB;
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

        $this->highPriorityFeePerKB = $result[self::FEE_STRATEGY_HIGH_PRIORITY];
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
        $privKey = $this->primaryPrivateKey->key();

        $pubKey = $this->primaryPrivateKey->publicKey();
        $address = $pubKey->getAddress()->getAddress();

        $signer = new MessageSigner(Bitcoin::getEcAdapter());
        $signed = $signer->sign($address, $privKey->getPrivateKey());

        return [$address, base64_encode($signed->getCompactSignature()->getBuffer()->getBinary())];
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
     * @param  integer $page        pagination: page number
     * @param  integer $limit       pagination: records per page (max 500)
     * @param  string  $sortDir     pagination: sort direction (asc|desc)
     * @param  boolean $zeroconf    include zero confirmation transactions
     * @return array                associative array containing the response
     */
    public function utxos($page = 1, $limit = 20, $sortDir = 'asc', $zeroconf = true) {
        return $this->sdk->walletUTXOs($this->identifier, $page, $limit, $sortDir, $zeroconf);
    }
}
