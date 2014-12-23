<?php

namespace Blocktrail\SDK\Bitcoin;

/**
 * Class BIP44
 *
 * BIP44 state, does not mutate itself but returns new instance everytime
 *
 * @package Blocktrail\SDK
 */
class BIP44 {
    protected $coin;
    protected $account;
    protected $external;
    protected $address;

    public function __construct($coin = 0, $account = 0, $external = true, $address = 0) {
        $this->coin = $coin;
        $this->account = $account;
        $this->external = self::castExternal($external);
        $this->address = $address;

    }

    /**
     * increase the address by 1  and return new BIP44
     *
     * @return BIP44
     */
    public function next() {
        return new static($this->coin, $this->account, $this->external, ($this->address ?: 0) + 1);
    }


    /**
     * change the account (and reset external to TRUE and account to 0) and return new BIP44
     *
     * @param $account
     * @return BIP44
     */
    public function account($account) {
        return new static($this->coin, $account);
    }


    /**
     * change to the external chain (and reset account to 0) and return new BIP44
     *
     * @param bool $external
     * @return BIP44
     */
    public function external($external = true) {
        $external = self::castExternal($external);
        if ($external === $this->external) {
            return $this;
        }

        return new static($this->coin, $this->account, $external);
    }

    /**
     * change to the internal chain (and reset account to 0) and return new BIP44
     *
     * @return BIP44
     */
    public function internal() {
        return $this->external(false);
    }

    /**
     * change to a different address and return new BIP44
     *
     * @param $address
     * @return BIP44
     */
    public function address($address) {
        if ($address === $this->address) {
            return $this;
        }

        return new static($this->coin, $this->account, $this->external, $address);
    }

    /**
     * reset the address counter reset to 0 and return new BIP44
     *
     * @return BIP44
     */
    public function reset() {
        return $this->address(0);
    }

    /**
     * get the BIP32Path for the current state
     *
     * @return BIP32Path
     */
    public function path() {
        return BIP32Path::path([
            "m", "44'", "{$this->coin}'", "{$this->account}'", $this->external ? 0 : 1, $this->address
        ]);
    }

    /**
     * get the BIP32Path for the current state up to the account
     *
     * @return BIP32Path
     */
    public function accountPath() {
        return BIP32Path::path([
            "m", "44'", "{$this->coin}'", "{$this->account}'"
        ]);
    }

    /**
     * static method to initialize class
     *
     * @param int  $coin
     * @param int  $account
     * @param bool $external
     * @param int  $address
     * @return BIP44
     */
    public static function BIP44($coin = 0, $account = 0, $external = true, $address = 0) {
        return new static($coin, $account, $external, $address);
    }

    public static function castExternal($external) {
        // 0 = external, 1 = internal
        if (is_numeric($external)) {
            return !(bool)(int)$external;
        } else {
            return (bool)$external;
        }
    }

    /**
     * @return string
     */
    public function __toString() {
        return (string)$this->path();
    }
}
