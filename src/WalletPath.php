<?php

namespace Blocktrail\SDK;

use Blocktrail\SDK\Bitcoin\BIP32Path;

/**
 * Class WalletPath
 *
 * Blocktrail Wallet custom BIP32 Path
 */
class WalletPath {
    protected $keyIndex;
    protected $chain;
    protected $address;

    public function __construct($keyIndex = 0, $chain = 0, $address = 0) {
        $this->keyIndex = $keyIndex;
        $this->chain = $chain;
        $this->address = $address;
    }

    /**
     * change the address and return new instance
     *
     * @param $address
     * @return WalletPath
     */
    public function address($address) {
        return new static($this->keyIndex, $this->chain, $address);
    }

    protected static function BIP32Path(array $path) {
        return BIP32Path::path(array_values(array_filter($path, function ($v) {
            return $v !== null;
        })));
    }

    /**
     * get the BIP32Path
     * m / key_index' / chain / address_index
     *
     * @return BIP32Path
     */
    public function path() {
        return self::BIP32Path([
            "m", "{$this->keyIndex}'", $this->chain, $this->address
        ]);
    }

    /**
     * get the BIP32Path for the backup key
     * m / key_index / chain / address_index
     *
     * @return BIP32Path
     */
    public function backupPath() {
        return self::BIP32Path([
            "m", $this->keyIndex, $this->chain, $this->address
        ]);
    }

    /**
     * get the BIP32Path for the key index
     * m / key_index'
     *
     * @return BIP32Path
     */
    public function keyIndexPath() {
        return self::BIP32Path([
            "m", "{$this->keyIndex}'"
        ]);
    }

    /**
     * get the BIP32Path for the key index for the backup key
     * m / key_index
     *
     * @return BIP32Path
     */
    public function keyIndexBackupPath() {
        return self::BIP32Path([
            "m", $this->keyIndex
        ]);
    }

    /**
     * static method to initialize class
     *
     * @param int  $keyIndex
     * @param int  $chain
     * @param int  $address
     * @return WalletPath
     */
    public static function create($keyIndex = 0, $chain = 0, $address = 0) {
        return new static($keyIndex, $chain, $address);
    }

    /**
     * @return string
     */
    public function __toString() {
        return (string)$this->path();
    }
}
