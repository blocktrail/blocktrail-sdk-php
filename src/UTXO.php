<?php

namespace Blocktrail\SDK;

use BitWasp\Bitcoin\Address\AddressInterface;
use BitWasp\Bitcoin\Script\ScriptInterface;
use BitWasp\Bitcoin\Transaction\TransactionOutput;

class UTXO {

    public $hash;
    public $index;
    public $value;

    /**
     * @var AddressInterface
     */
    public $address;

    /**
     * @var ScriptInterface
     */
    public $scriptPubKey;
    public $path;

    /**
     * @var ScriptInterface
     */
    public $redeemScript;

    /**
     * @var ScriptInterface|null
     */
    public $witnessScript;

    /**
     * @var string
     */
    public $signMode;

    /**
     * UTXO constructor.
     * @param $hash
     * @param $index
     * @param null $value
     * @param AddressInterface|null $address
     * @param ScriptInterface|null $scriptPubKey
     * @param null $path
     * @param ScriptInterface|null $redeemScript
     * @param ScriptInterface|null $witnessScript
     * @param string $signMode
     */
    public function __construct(
        $hash,
        $index,
        $value = null,
        AddressInterface $address = null,
        ScriptInterface $scriptPubKey = null,
        $path = null,
        ScriptInterface $redeemScript = null,
        ScriptInterface $witnessScript = null,
        $signMode = SignInfo::MODE_SIGN
    ) {
        $this->hash = $hash;
        $this->index = $index;
        $this->value = $value;
        $this->address = $address;
        $this->scriptPubKey = $scriptPubKey;
        $this->path = $path;
        $this->redeemScript = $redeemScript;
        $this->witnessScript = $witnessScript;
        $this->signMode = $signMode;
    }

    /**
     * @return SignInfo
     */
    public function getSignInfo() {
        return new SignInfo(new TransactionOutput($this->value, $this->scriptPubKey), $this->signMode, $this->path, $this->redeemScript, $this->witnessScript);
    }
}
