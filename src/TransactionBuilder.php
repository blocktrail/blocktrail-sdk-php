<?php

namespace Blocktrail\SDK;

use BitWasp\BitcoinLib\BitcoinLib;
use BitWasp\BitcoinLib\RawTransaction;
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
     * @param string $address                when NULL we'll use the data API to fetch the address
     * @param string $scriptPubKey           as HEX, when NULL we'll use the data API to fetch the scriptpubkey
     * @param string $path                   when NULL we'll use the API to determine the path for the specified address
     * @param string $redeemScript           when NULL we'll use the path to determine the redeemscript
     * @return $this
     */
    public function spendOutput($txId, $index, $value = null, $address = null, $scriptPubKey = null, $path = null, $redeemScript = null) {
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
     * @param string $address
     * @param int    $value
     * @return $this
     * @throws \Exception
     */
    public function addRecipient($address, $value) {
        if (!BitcoinLib::validate_address($address)) {
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
     */
    public function addOutput($output) {
        $this->outputs[] = $output;
    }

    /**
     * add OP_RETURN output
     *
     * $data will be bin2hex and will be prefixed with a proper OP_PUSHDATA
     *
     * @param string $data
     * @param bool   $allowNonStandard  when TRUE will allow scriptPubKey > 40 bytes (so $data > 39 bytes)
     * @throws BlocktrailSDKException
     */
    public function addOpReturn($data, $allowNonStandard = false) {
        $pushdata = RawTransaction::pushdata(bin2hex($data));

        if (!$allowNonStandard && strlen($pushdata) / 2 > 40) {
            throw new BlocktrailSDKException("OP_RETURN data should be <= 39 bytes to remain standard!");
        }

        $this->addOutput([
            'scriptPubKey' => self::OP_RETURN . RawTransaction::pushdata(bin2hex($data)),
            'value' => 0
        ]);
    }

    /**
     * @return array
     */
    public function getOutputs() {
        return $this->outputs;
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
