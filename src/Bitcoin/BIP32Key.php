<?php

namespace Blocktrail\SDK\Bitcoin;

use BitWasp\BitcoinLib\BIP32;

/**
 * Class BIP32Key
 *
 * Container for a BIP32 key and path
 */
class BIP32Key {
    /**
     * @var string
     */
    private $key;

    /**
     * @var BIP32Path
     */
    private $path;

    /**
     * @var string|null
     */
    private $publicKey = null;

    /**
     * @var BIP32Key[]
     */
    private $derivations = [];

    /**
     * @param string|array  $key        if it's an array then it will be split into $key and $path
     * @param string|null   $path
     * @throws \Exception
     */
    public function __construct($key, $path = null) {
        if (is_array($key) && count($key) == 2) {
            $this->key = $key[0];
            $this->path = BIP32Path::path($key[1]);
        } else if (is_string($key) && is_string($path) && strlen($key) && strlen($path)) {
            $this->key = $key;
            $this->path = BIP32Path::path($path);
        } else {
            throw new \Exception("Bad input");
        }
    }

    /**
     * static method to initialize class
     *
     * @param string|array  $key
     * @param string|null   $path
     * @return BIP32Key
     */
    public static function create($key, $path = null) {
        return new BIP32Key($key, $path);
    }

    /**
     * @return string
     */
    public function key() {
        return $this->key;
    }

    /**
     * @return BIP32Path
     */
    public function path() {
        return $this->path;
    }

    /**
     * get tuple of the key and path, the way BitcoinLib likes it
     *
     * @return array        [key, path]
     */
    public function tuple() {
        return [$this->key, (string)$this->path];
    }

    /**
     * @return BIP32Path
     */
    public function bip32Path() {
        return BIP32Path::path($this->path);
    }

    /**
     * build child key
     *
     * @param string|BIP32Path  $path
     * @return BIP32Key
     * @throws \Exception
     */
    public function buildKey($path) {
        if (!isset($this->derivations[(string)$path])) {
            $key = BIP32::build_key($this->tuple(), (string)$path);
            $this->derivations[(string)$path] = $key;
        }

        return new BIP32Key($this->derivations[(string)$path]);
    }

    /**
     * get the plain public key for the current BIP32 key
     *
     * @return string
     */
    public function publicKey() {
        // if this is a BIP32 Private key then we first build the public key
        //  that way it will be cached nicely
        if (!$this->path->isPublicPath()) {
            return $this->buildKey($this->path->publicPath())->publicKey();
        } else {
            if (is_null($this->publicKey)) {
                $this->publicKey = BIP32::extract_public_key($this->tuple());
            }

            return $this->publicKey;
        }
    }
}
