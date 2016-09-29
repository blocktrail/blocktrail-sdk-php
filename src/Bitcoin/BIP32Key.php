<?php

namespace Blocktrail\SDK\Bitcoin;

use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Key\PublicKey;
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PublicKeyInterface;
use BitWasp\Bitcoin\Key\Deterministic\HierarchicalKey;

/**
 * Class BIP32Key
 *
 * Container for a BIP32 key and path
 */
class BIP32Key {
    /**
     * @var HierarchicalKey
     */
    private $key;

    /**
     * @var BIP32Path
     */
    private $path;

    /**
     * @var string|null
     */
    private $publicKeyHex = null;

    /**
     * @var BIP32Key[]
     */
    private $derivations = [];

    /**
     * @param HierarchicalKey $key
     * @param string|null     $path
     * @throws \Exception
     */
    public function __construct(HierarchicalKey $key, $path = null) {
        $this->key = $key;
        $this->path = BIP32Path::path($path);

        return;

        if (is_array($key) && count($key) == 2) {
            $this->key = $key[0];
            $this->path = BIP32Path::path($key[1]);
        } elseif (is_string($key) && is_string($path) && strlen($key) && strlen($path)) {
            $this->key = $key;
            $this->path = BIP32Path::path($path);
        } else {
            throw new \Exception("Bad input");
        }
    }

    /**
     * static method to initialize class
     *
     * @param HierarchicalKey $key
     * @param string|null     $path
     * @return BIP32Key
     */
    public static function create(HierarchicalKey $key, $path = null) {
        return new BIP32Key($key, $path);
    }

    /**
     * @return HierarchicalKey
     */
    public function key() {
        return $this->key;
    }

    /**
     * @return PublicKeyInterface
     */
    public function publicKey() {
        return $this->key->getPublicKey();
    }

    /**
     * get the HEX of the plain public key for the current BIP32 key
     *
     * @return string
     */
    public function publicKeyHex() {
        // if this is a BIP32 Private key then we first build the public key
        //  that way it will be cached nicely
        if (!$this->path->isPublicPath()) {
            return $this->buildKey($this->path->publicPath())->publicKey()->getHex();
        } else {
            if (is_null($this->publicKeyHex)) {
                $this->publicKeyHex = $this->key->getPublicKey()->getHex();
            }

            return $this->publicKeyHex;
        }
    }

    /**
     * @return BIP32Path
     */
    public function path() {
        return $this->path;
    }

    /**
     * @return BIP32Path
     */
    public function bip32Path() {
        return BIP32Path::path($this->path);
    }

    public function tuple() {
        return [$this->key->toExtendedKey(), (string)$this->path];
    }

    /**
     * build child key
     *
     * @param string|BIP32Path  $path
     * @return BIP32Key
     * @throws \Exception
     */
    public function buildKey($path) {
        $path = BIP32Path::path($path);
        $originalPath = (string)$path;

        if (!isset($this->derivations[$originalPath])) {
            $key = $this->key;

            $toPublic = $path[0] === "M" && $this->path[0] === "m";
            if ($toPublic) {
                $path = $path->privatePath();
            }

            assert(strpos(strtolower((string)$path), strtolower((string)$this->path)) === 0);

            $path = substr((string)$path, strlen((string)$this->path));

            if (substr($path, 0, 1) == "/") {
                $path = substr($path, 1);
            }

            if (strlen($path)) {
                $key = $key->derivePath($path);
            }

            if ($toPublic) {
                $key = $key->toPublic();
            }

            $this->derivations[$originalPath] = BIP32Key::create($key, $originalPath);
        }

        return $this->derivations[$originalPath];
    }
}
