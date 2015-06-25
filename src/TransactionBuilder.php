<?php

namespace Blocktrail\SDK;

use BitWasp\BitcoinLib\BitcoinLib;

/**
 * Class TransactionBuilder
 *
 * still WIP so unsure if API remains the same, keep this in mind when updating the SDK!
 */
class TransactionBuilder {

    private $utxos = [];
    private $outputs = [];

    private $changeAddress = null;
    private $randomizeChangeOutput = true;

    private $fee = null;

    private $validateChange = null;
    private $validateFee = null;

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
        $this->utxos[] = [
            'hash' => $txId,
            'idx' => $index,
            'value' => $value,
            'address' => $address,
            'scriptpubkey_hex' => $scriptPubKey,
            'path' => $path,
            'redeem_script' => $redeemScript,
        ];

        return $this;
    }

    /**
     * @return array[]
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

        $this->outputs[] = ['address' => $address, 'value' => $value];

        return $this;
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
     * @param int $change
     * @return $this
     */
    public function validateChange($change) {
        $this->validateChange = $change;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getValidateChange() {
        return $this->validateChange;
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
