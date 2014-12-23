<?php

namespace Blocktrail\SDK\Bitcoin;

/**
 * Class BIP32Path
 *
 * BIP32 path, does not mutate itself but returns new instance everytime
 *
 * @package Blocktrail\SDK
 */
class BIP32Path implements \ArrayAccess {
    protected $path;

    public function __construct($path) {
        $this->path = is_array($path) ? $path : explode("/", $path);
    }

    /**
     * increase the last level of the path by 1 and return the new path
     *
     * @return BIP32Path
     */
    public function next() {
        $path = $this->path;

        $last = array_pop($path);

        if ($hardened = (strpos($last, "'") !== false)) {
            $last = str_replace("'", "", $last);
        }

        $last = (int)$last;
        $last += 1;

        if ($hardened) {
            $last .= "'";
        }

        $path[] = $last;

        return new static($path);
    }

    /**
     * pop off one level of the path and return the new path
     *
     * @return BIP32Path
     */
    public function parent() {
        $path = $this->path;

        array_pop($path);

        if (empty($path)) {
            return false;
        }

        return new static($path);
    }

    /**
     * get child $child of the current path and return the new path
     *
     * @param $child
     * @return BIP32Path
     */
    public function child($child) {
        $path = $this->path;

        $path[] = $child;

        return new static($path);
    }

    /**
     * pop off one level of the path and add $last and return the new path
     *
     * @param $last
     * @return BIP32Path
     */
    public function last($last) {
        $path = $this->path;

        array_pop($path);
        $path[] = $last;

        return new static($path);
    }

    /**
     * harden the last level of the path and return the new path
     *
     * @return BIP32Path
     */
    public function hardened() {
        $path = $this->path;

        $last = array_pop($path);

        if ($hardened = (strpos($last, "'") !== false)) {
            return $this;
        }

        $last .= "'";

        $path[] = $last;

        return new static($path);
    }

    /**
     * unharden the last level of the path and return the new path
     *
     * @return BIP32Path
     */
    public function unhardened() {
        $path = $this->path;

        $last = array_pop($path);

        if (!($hardened = (strpos($last, "'") !== false))) {
            return $this;
        }

        $last = str_replace("'", "", $last);

        $path[] = $last;

        return new static($path);
    }

    /**
     * change the path to be for the public key (starting with M/) and return the new path
     *
     * @return BIP32Path
     */
    public function publicPath() {
        $path = $this->path;

        if ($path[0] === "M") {
            return $this;
        } else if ($path[0] === "m") {
            $path[0] = "M";

            return new static($path);
        } else {
            return false;
        }
    }

    /**
     * change the path to be for the private key (starting with m/) and return the new path
     *
     * @return BIP32Path
     */
    public function privatePath() {
        $path = $this->path;

        if ($path[0] === "m") {
            return $this;
        } else if ($path[0] === "M") {
            $path[0] = "m";

            return new static($path);
        } else {
            return false;
        }
    }

    /**
     * get the string representation of the path
     *
     * @return string
     */
    public function getPath() {
        return implode("/", $this->path);
    }

    /**
     * get the last part of the path
     *
     * @return string
     */
    public function getLast() {
        return $this->path[count($this->path)-1];
    }

    /**
     * check if the last level of the path is hardened
     *
     * @return bool
     */
    public function isHardened() {
        $path = $this->path;

        $last = array_pop($path);

        return strpos($last, "'") !== false;
    }

    /**
     * check if the last level of the path is hardened
     *
     * @return bool
     */
    public function isPublicPath() {
        $path = $this->path;

        return $path[0] == "M";
    }

    /**
     * static method to initialize class
     *
     * @param $path
     * @return BIP32Path
     */
    public static function path($path) {
        if ($path instanceof static) {
            return $path;
        }

        return new static($path);
    }

    /**
     * count the levels in the path (including master)
     *
     * @return int
     */
    public function count() {
        return count($this->path);
    }


    public function offsetExists($offset) {
        return isset($this->path[$offset]);
    }

    public function offsetGet($offset) {
        return isset($this->path[$offset]) ? $this->path[$offset] : null;
    }

    public function offsetSet($offset, $value) {
        throw new \Exception("Not implemented");
    }

    public function offsetUnset($offset) {
        throw new \Exception("Not implemented");
    }

    /**
     * @return string
     */
    public function __toString() {
        return $this->getPath();
    }
}
