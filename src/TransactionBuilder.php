<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Address\AddressFactory;
use BitWasp\Bitcoin\Address\AddressInterface;
use BitWasp\Bitcoin\Script\Script;
use BitWasp\Bitcoin\Script\ScriptFactory;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Buffertools\Buffer;
use Blocktrail\SDK\Exceptions\BlocktrailSDKException;

/**
 * Class TransactionBuilder
 *
 * still WIP so unsure if API remains the same, keep this in mind when updating the SDK!
 */
class TransactionBuilder {

    const OP_RETURN = '6a';

    /**
     * @var UTXO[]
     */
    private $utxos = [];

    /**
     * @var array[]
     */
    private $outputs = [];

    private $changeAddress = null;
    private $randomizeChangeOutput = true;

    private $fee = null;

    private $validateFee = null;

    private $feeStrategy = Wallet::FEE_STRATEGY_OPTIMAL;

    public function __construct() {
    }

    /**
     * @param string $txId                   transactionId (hash)
     * @param int    $index                  index of the output being spent
     * @param string $value                  when NULL we'll use the data API to fetch the value
     * @param AddressInterface|string $address                when NULL we'll use the data API to fetch the address
     * @param ScriptInterface|string $scriptPubKey           as HEX, when NULL we'll use the data API to fetch the scriptpubkey
     * @param string $path                   when NULL we'll use the API to determine the path for the specified address
     * @param ScriptInterface|string $redeemScript           when NULL we'll use the path to determine the redeemscript
     * @return $this
     */
    public function spendOutput($txId, $index, $value = null, $address = null, $scriptPubKey = null, $path = null, $redeemScript = null) {
        $address = $address instanceof AddressInterface ? $address : AddressFactory::fromString($address);
        $scriptPubKey = ($scriptPubKey instanceof ScriptInterface)
            ? $scriptPubKey
            : (ctype_xdigit($scriptPubKey) ? ScriptFactory::fromHex($scriptPubKey) : null);
        $redeemScript = ($redeemScript instanceof ScriptInterface)
            ? $redeemScript
            : (ctype_xdigit($redeemScript) ? ScriptFactory::fromHex($redeemScript) : null);

        $this->utxos[] = new UTXO($txId, $index, $value, $address, $scriptPubKey, $path, $redeemScript);

        return $this;
    }

    /**
     * @return UTXO[]
     */
    public function getUtxos() {
        return $this->utxos;
    }

    /**
     * replace the currently set UTXOs with a new set
     *
     * @param UTXO[] $utxos
     * @return $this
     */
    public function setUtxos(array $utxos) {
        $this->utxos = $utxos;

        return $this;
    }

    /**
     * @param string $address
     * @param int    $value
     * @return $this
     * @throws \Exception
     */
    public function addRecipient($address, $value) {
        if (AddressFactory::fromString($address)->getAddress() != $address) {
            throw new \Exception("Invalid address [{$address}]");
        }

        // using this 'dirty' way of checking for a float since there's no other reliable way in PHP
        if (!is_int($value)) {
            throw new \Exception("Values should be in Satoshis (int)");
        }

        if ($value <= Blocktrail::DUST) {
            throw new \Exception("Values should be more than dust (" . Blocktrail::DUST . ")");
        }

        $this->addOutput([
            'address' => $address,
            'value' => $value
        ]);

        return $this;
    }

    /**
     * add a 'raw' output, normally addRecipient or addOpReturn should be used
     *
     * @param array $output     [value => int, address => string]
     *                          or [value => int, scriptPubKey => string] (scriptPubKey should be hex)
     * @return $this
     */
    public function addOutput($output) {
        $this->outputs[] = $output;

        return $this;
    }

    /**
     * @param $idx
     * @param $output
     * @return $this
     */
    public function replaceOutput($idx, $output) {
        $this->outputs[$idx] = $output;

        return $this;
    }

    /**
     * @param $idx
     * @param $value
     * @return $this
     * @throws \Exception
     */
    public function updateOutputValue($idx, $value) {
        // using this 'dirty' way of checking for a float since there's no other reliable way in PHP
        if (!is_int($value)) {
            throw new \Exception("Values should be in Satoshis (int)");
        }

        if ($value <= Blocktrail::DUST) {
            throw new \Exception("Values should be more than dust (" . Blocktrail::DUST . ")");
        }

        if (!isset($this->outputs[$idx])) {
            throw new \Exception("No output for index [{$idx}]");
        }

        $this->outputs[$idx]['value'] = $value;

        return $this;
    }

    /**
     * add OP_RETURN output
     *
     * $data will be bin2hex and will be prefixed with a proper OP_PUSHDATA
     *
     * @param string $data
     * @param bool   $allowNonStandard  when TRUE will allow scriptPubKey > 80 bytes (so $data > 80 bytes)
     * @return $this
     * @throws BlocktrailSDKException
     */
    public function addOpReturn($data, $allowNonStandard = false) {
        if (!$allowNonStandard && strlen($data) / 2 > 79) {
            throw new BlocktrailSDKException("OP_RETURN data should be <= 79 bytes to remain standard!");
        }

        $script = ScriptFactory::create()
            ->op('OP_RETURN')
            ->push(new Buffer($data))
            ->getScript()
        ;

        $this->addOutput([
            'scriptPubKey' => $script,
            'value' => 0
        ]);

        return $this;
    }

    /**
     * @param bool $json return data for JSON return (so objects -> string)
     * @return array
     */
    public function getOutputs($json = false) {
        return array_map(function ($output) use ($json) {
            $result = $output;

            if ($json) {
                if (isset($result['scriptPubKey']) && $result['scriptPubKey'] instanceof ScriptInterface) {
                    $result['scriptPubKey'] = $result['scriptPubKey']->getHex();
                }
                if (isset($result['address']) && $result['address'] instanceof AddressInterface) {
                    $result['address'] = $result['address']->getAddress();
                }
            }

            return $result;
        }, $this->outputs);
    }

    /**
     * set change address
     *
     * @param string $address
     * @return $this
     */
    public function setChangeAddress($address) {
        $this->changeAddress = $address;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getChangeAddress() {
        return $this->changeAddress;
    }

    /**
     * @param string $feeStrategy
     * @return $this
     * @throws BlocktrailSDKException
     */
    public function setFeeStrategy($feeStrategy) {
        $this->feeStrategy = $feeStrategy;

        if (!in_array($feeStrategy, [Wallet::FEE_STRATEGY_BASE_FEE, Wallet::FEE_STRATEGY_OPTIMAL, Wallet::FEE_STRATEGY_LOW_PRIORITY])) {
            throw new BlocktrailSDKException("Unknown feeStrategy [{$feeStrategy}]");
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getFeeStrategy() {
        return $this->feeStrategy;
    }

    /**
     * @param bool $randomizeChangeOutput
     * @return $this
     */
    public function randomizeChangeOutput($randomizeChangeOutput = true) {
        $this->randomizeChangeOutput = $randomizeChangeOutput;

        return $this;
    }

    /**
     * @return bool
     */
    public function shouldRandomizeChangeOuput() {
        return $this->randomizeChangeOutput;
    }

    /**
     * set desired fee (normally automatically calculated)
     *
     * @param int $value
     * @return $this
     */
    public function setFee($value) {
        // using this 'dirty' way of checking for a float since there's no other reliable way in PHP
        if (!is_int($value)) {
            throw new \Exception("Fee should be in Satoshis (int) - can be 0");
        }

        $this->fee = $value;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getFee() {
        return $this->fee;
    }

    /**
     * @param int $fee
     * @return $this
     */
    public function validateFee($fee) {
        $this->validateFee = $fee;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getValidateFee() {
        return $this->validateFee;
    }
}
