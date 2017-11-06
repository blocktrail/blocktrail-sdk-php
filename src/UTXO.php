<?php

namespace Blocktrail\SDK;

use BitWasp\BitcoinLib\BIP32;
use BitWasp\BitcoinLib\BIP39\BIP39;
use BitWasp\BitcoinLib\BitcoinLib;
use BitWasp\BitcoinLib\RawTransaction;
use Blocktrail\SDK\Bitcoin\BIP32Key;
use Blocktrail\SDK\Bitcoin\BIP32Path;

class UTXO implements \ArrayAccess {

    const MODE_DONTSIGN = 'dont_sign';
    const MODE_SIGN = 'sign';

    public $hash;
    public $index;
    public $value;
    public $address;
    public $scriptPubKeyHex;
    public $path;
    public $redeemScript;
    public $signMode;

    public function __construct($hash, $index, $value = null, $address = null, $scriptPubKeyHex = null, $path = null, $redeemScript = null, $signMode = self::MODE_SIGN) {
        $this->hash = $hash;
        $this->index = $index;
        $this->value = $value;
        $this->address = $address;
        $this->scriptPubKeyHex = $scriptPubKeyHex;
        $this->path = $path;
        $this->redeemScript = $redeemScript;
        $this->signMode = $signMode ?: self::MODE_SIGN;
    }

    public function offsetExists($offset) {

    }

    public function offsetGet($offset) {

    }

    public function offsetSet($offset, $value) {

    }

    public function offsetUnset($offset) {

    }
}
