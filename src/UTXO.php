<?php

namespace Blocktrail\SDK;

class UTXO {

    public $hash;
    public $index;
    public $value;
    public $address;
    public $scriptPubKeyHex;
    public $path;
    public $redeemScript;

    public function __construct($hash, $index, $value = null, $address = null, $scriptPubKeyHex = null, $path = null, $redeemScript = null) {
        $this->hash = $hash;
        $this->index = $index;
        $this->value = $value;
        $this->address = $address;
        $this->scriptPubKeyHex = $scriptPubKeyHex;
        $this->path = $path;
        $this->redeemScript = $redeemScript;
    }
}
